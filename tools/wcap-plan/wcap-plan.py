#!/usr/bin/env -S uv run --script
# /// script
# requires-python = ">=3.11"
# dependencies = [
#   "httpx>=0.27",
#   "PyYAML>=6.0",
# ]
# ///
"""
wcap-plan — fetch your team's plan, edit in $EDITOR, ship the diff.

Usage:
    export WCAP_TOKEN='1|abc...'           # mint at /profile in WCAP
    export WCAP_BASE_URL='https://...'     # optional, defaults to local lando
    ./tools/wcap-plan/wcap-plan.py         # or: uv run tools/wcap-plan/wcap-plan.py
"""

from __future__ import annotations

import argparse
import os
import subprocess
import sys
import tempfile
from datetime import date, datetime, timedelta
from pathlib import Path

import httpx
import yaml


# A SafeLoader variant that leaves ISO dates as strings rather than coercing
# them to datetime.date — we want round-trip equality with our generated YAML
# and we send dates back to the API as strings.
class WcapLoader(yaml.SafeLoader):
    pass


WcapLoader.yaml_implicit_resolvers = {
    key: [(tag, regexp) for tag, regexp in resolvers if tag != "tag:yaml.org,2002:timestamp"]
    for key, resolvers in yaml.SafeLoader.yaml_implicit_resolvers.items()
}


DEFAULT_BASE_URL = "https://wcap.lndo.site"

AVAIL_TO_INT = {"not_available": 0, "remote": 1, "onsite": 2}
AVAIL_FROM_INT = {v: k for k, v in AVAIL_TO_INT.items()}

CATEGORIES = {"support", "project", "admin", "leave"}


def die(msg: str, code: int = 1) -> None:
    print(f"error: {msg}", file=sys.stderr)
    sys.exit(code)


def make_client() -> httpx.Client:
    token = os.environ.get("WCAP_TOKEN")
    base = os.environ.get("WCAP_BASE_URL", DEFAULT_BASE_URL)
    insecure = os.environ.get("WCAP_INSECURE") == "1"

    if not token:
        die("WCAP_TOKEN not set. Mint a token in your WCAP profile and export it.")

    return httpx.Client(
        base_url=base,
        headers={"Authorization": f"Bearer {token}", "Accept": "application/json"},
        timeout=20.0,
        verify=not insecure,
    )


def call_api(client: httpx.Client, method: str, path: str, **kwargs) -> dict:
    r = client.request(method, path, **kwargs)
    if r.status_code == 401:
        die("authentication failed (401). Check WCAP_TOKEN.")
    if r.status_code == 403:
        die("forbidden (403). Your role doesn't have access to this endpoint.")
    if not r.is_success:
        try:
            body = r.json()
            msg = body.get("message") or body
        except ValueError:
            msg = r.text
        die(f"{method} {path} failed ({r.status_code}): {msg}")
    return r.json()


def fetch_team(client: httpx.Client) -> list[dict]:
    return call_api(client, "GET", "/api/v1/manager/team-members")["team_members"]


def fetch_plan(client: httpx.Client, user_id: int) -> dict:
    return call_api(client, "GET", f"/api/v1/manager/team-members/{user_id}/plan")


def fetch_plan_window(client: httpx.Client, user_id: int, from_iso: str, to_iso: str) -> dict:
    return call_api(
        client,
        "GET",
        f"/api/v1/manager/team-members/{user_id}/plan",
        params={"filter[from]": from_iso, "filter[to]": to_iso},
    )


def previous_window(start_iso: str, end_iso: str) -> tuple[str, str]:
    """Return the equivalent date window 14 days earlier — i.e. last fortnight."""
    start = datetime.strptime(start_iso, "%Y-%m-%d").date()
    end = datetime.strptime(end_iso, "%Y-%m-%d").date()
    return (start - timedelta(days=14)).isoformat(), (end - timedelta(days=14)).isoformat()


def fetch_locations(client: httpx.Client) -> list[dict]:
    return call_api(client, "GET", "/api/v1/locations")["locations"]


def weekdays_between(start_iso: str, end_iso: str) -> list[date]:
    start = datetime.strptime(start_iso, "%Y-%m-%d").date()
    end = datetime.strptime(end_iso, "%Y-%m-%d").date()
    days: list[date] = []
    cursor = start
    while cursor <= end:
        if cursor.weekday() < 5:
            days.append(cursor)
        cursor += timedelta(days=1)
    return days


def entry_to_row(entry: dict | None, day: date, carry_forward: dict[int, dict] | None = None) -> dict:
    """Render an API entry as a YAML-ready row.

    If `entry` is None and `carry_forward` is provided, fall back to the prior
    fortnight's entry for the same weekday (Mon→Mon etc.) — the projection that
    powers --carry-forward mode.
    """
    source = entry
    if source is None and carry_forward is not None:
        source = carry_forward.get(day.weekday())

    if source is None:
        return {
            "date": day.isoformat(),
            "day": day.strftime("%a"),
            "location": None,
            "availability": "not_available",
            "category": None,
            "holiday": False,
            "note": "",
        }
    return {
        "date": day.isoformat(),
        "day": day.strftime("%a"),
        "location": source.get("location"),
        "availability": AVAIL_FROM_INT.get(source.get("availability_status", 0), "not_available"),
        "category": source.get("category"),
        "holiday": bool(source.get("is_holiday")),
        "note": source.get("note") or "",
    }


def to_diff_record(plan: dict) -> dict:
    """Build the same dict shape parse_edited() produces, directly from an API
    plan response. Used as the diff baseline so carry-forward projections show
    up as '+ NEW' rather than as no-ops."""
    days = weekdays_between(plan["date_range"]["start"], plan["date_range"]["end"])
    existing = {e["entry_date"]: e for e in plan["entries"]}
    return {
        "name": plan["user"]["name"],
        "id": plan["user"]["id"],
        "days": [entry_to_row(existing.get(d.isoformat()), d) for d in days],
    }


def build_carry_forward_lookup(prior_plan: dict) -> dict[int, dict]:
    """Map weekday-int (0=Mon … 4=Fri) → entry from the prior fortnight.

    If the prior fortnight has two Mondays with entries, the later one wins —
    closer to "what's the current pattern" than "what was true two weeks ago".
    """
    lookup: dict[int, dict] = {}
    for entry in sorted(prior_plan.get("entries", []), key=lambda e: e["entry_date"]):
        d = datetime.strptime(entry["entry_date"], "%Y-%m-%d").date()
        if d.weekday() < 5:
            lookup[d.weekday()] = entry
    return lookup


def yaml_inline(value) -> str:
    """Render a single value as YAML inline. None → ~, bool → yes/no, str → bare or quoted."""
    if value is None:
        return "~"
    if isinstance(value, bool):
        return "yes" if value else "no"
    if isinstance(value, str):
        if value == "" or any(c in value for c in ":#'\"\n,[]{}"):
            return '"' + value.replace("\\", "\\\\").replace('"', '\\"') + '"'
        return value
    return str(value)


def yaml_quoted(value) -> str:
    """Always render a string as a quoted YAML scalar — safe for free-text fields
    where a numeric-looking value would otherwise round-trip as an int."""
    s = "" if value is None else str(value)
    return '"' + s.replace("\\", "\\\\").replace('"', '\\"') + '"'


def render(
    team_plans: list[dict],
    locations: list[dict],
    carry_forward_by_user: dict[int, dict[int, dict]] | None = None,
) -> str:
    location_slugs = [loc["value"] for loc in locations] or ["(none configured)"]
    lines: list[str] = []
    lines.append("# WCAP team plan — edit and save to ship changes")
    lines.append("#")
    lines.append("# Locations: " + ", ".join(location_slugs))
    lines.append("# Availability: onsite | remote | not_available")
    lines.append("# Categories: support | project | admin | leave")
    lines.append("#")
    lines.append("# Use ~ for an empty location/category. Use yes/no for holiday.")
    lines.append("# Don't change the date or id fields — they're used to match entries.")
    if carry_forward_by_user:
        lines.append("#")
        lines.append("# CARRY-FORWARD MODE: blank days have been pre-filled with last fortnight's")
        lines.append("# pattern (matched by weekday). On save, those cells will be created as new")
        lines.append("# entries. Tweak holidays/training/etc. before confirming.")
    lines.append("#")
    if team_plans:
        rng = team_plans[0]["date_range"]
        lines.append(f"# Date range: {rng['start']} → {rng['end']}")
    lines.append("")

    for plan in team_plans:
        user = plan["user"]
        days = weekdays_between(plan["date_range"]["start"], plan["date_range"]["end"])
        existing = {e["entry_date"]: e for e in plan["entries"]}
        cf_map = (carry_forward_by_user or {}).get(user["id"])

        lines.append(f"- name: {yaml_inline(user['name'])}")
        lines.append(f"  id: {user['id']}")
        lines.append("  days:")

        for d in days:
            row = entry_to_row(existing.get(d.isoformat()), d, carry_forward=cf_map)
            inline = (
                f"date: {row['date']}, day: {row['day']}, "
                f"location: {yaml_inline(row['location'])}, "
                f"availability: {row['availability']}, "
                f"category: {yaml_inline(row['category'])}, "
                f"holiday: {yaml_inline(row['holiday'])}, "
                f"note: {yaml_quoted(row['note'])}"
            )
            lines.append(f"  - {{{inline}}}")
        lines.append("")

    return "\n".join(lines) + "\n"


def open_in_editor(text: str) -> str:
    editor = os.environ.get("EDITOR") or os.environ.get("VISUAL") or "vi"
    with tempfile.NamedTemporaryFile(
        mode="w", suffix=".yaml", prefix="wcap-plan-", delete=False
    ) as tmp:
        tmp.write(text)
        path = Path(tmp.name)
    try:
        subprocess.run([editor, str(path)], check=True)
        return path.read_text()
    finally:
        try:
            path.unlink()
        except FileNotFoundError:
            pass


def parse_edited(yaml_text: str) -> list[dict]:
    try:
        data = yaml.load(yaml_text, Loader=WcapLoader)
    except yaml.YAMLError as exc:
        die(f"could not parse the edited file as YAML:\n{exc}")
    if not isinstance(data, list):
        die("expected a YAML list of team members at top level — was the file mangled?")
    for person in data:
        for key in ("name", "id", "days"):
            if key not in person:
                die(f"team member missing '{key}': {person!r}")
        for day in person["days"]:
            for key in ("date", "location", "availability", "category", "holiday", "note"):
                if key not in day:
                    die(f"day for {person['name']} missing '{key}': {day!r}")
    return data


def normalise(value):
    """Treat None and empty/whitespace-only strings as the same value for diffing."""
    if value is None:
        return None
    if isinstance(value, str) and value.strip() == "":
        return None
    return value


def diff_plans(old: list[dict], new: list[dict]) -> list[tuple[dict, list[tuple[dict | None, dict]]]]:
    old_by_id = {p["id"]: p for p in old}
    results: list[tuple[dict, list[tuple[dict | None, dict]]]] = []

    for person in new:
        if person["id"] not in old_by_id:
            die(f"unknown user id {person['id']} ({person.get('name')!r}) — don't add or change ids in the file")

        old_days = {d["date"]: d for d in old_by_id[person["id"]]["days"]}
        changed: list[tuple[dict | None, dict]] = []

        for day in person["days"]:
            old_day = old_days.get(day["date"])
            if old_day is None:
                changed.append((None, day))
                continue
            for k in ("location", "availability", "category", "holiday", "note"):
                if normalise(old_day.get(k)) != normalise(day.get(k)):
                    changed.append((old_day, day))
                    break

        if changed:
            results.append((person, changed))

    return results


def show_diff(diff) -> bool:
    if not diff:
        print("No changes to apply.")
        return False
    print("\nChanges to apply:\n")
    for person, changes in diff:
        print(f"  {person['name']} (id {person['id']}):")
        for old, new in changes:
            day_label = f"{new['date']} {new.get('day', '')}".strip()
            if old is None:
                print(
                    f"    + {day_label}: NEW → "
                    f"location={new['location']}, availability={new['availability']}, "
                    f"category={new['category']}, holiday={new['holiday']}, note={new['note']!r}"
                )
                continue
            parts = []
            for k in ("location", "availability", "category", "holiday", "note"):
                if normalise(old.get(k)) != normalise(new.get(k)):
                    parts.append(f"{k}: {old.get(k)!r} → {new.get(k)!r}")
            print(f"    ~ {day_label}: " + ", ".join(parts))
        print()
    return True


def confirm(prompt: str) -> bool:
    try:
        ans = input(prompt + " [y/N]: ").strip().lower()
    except EOFError:
        return False
    return ans in ("y", "yes")


def push_changes(client: httpx.Client, diff, location_slugs: set[str]) -> None:
    for person, changes in diff:
        entries = []
        for _, new in changes:
            avail = new["availability"]
            if avail not in AVAIL_TO_INT:
                die(f"invalid availability for {person['name']} on {new['date']}: {avail!r}")

            category = new["category"]
            if category is not None and category not in CATEGORIES:
                die(f"invalid category for {person['name']} on {new['date']}: {category!r}")

            location = new["location"]
            if location is not None and location not in location_slugs:
                die(f"unknown location slug for {person['name']} on {new['date']}: {location!r}")

            entries.append(
                {
                    "entry_date": new["date"],
                    "location": location,
                    "availability_status": AVAIL_TO_INT[avail],
                    "category": category,
                    "is_holiday": bool(new["holiday"]),
                    "note": new["note"] or "",
                }
            )

        path = f"/api/v1/manager/team-members/{person['id']}/plan"
        call_api(client, "POST", path, json={"entries": entries})
        print(f"  ✓ {person['name']}: {len(entries)} entr{'y' if len(entries) == 1 else 'ies'} updated")


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        prog="wcap-plan",
        description="Edit your team's WCAP plan in $EDITOR, then ship the diff.",
    )
    parser.add_argument(
        "-c",
        "--carry-forward",
        action="store_true",
        help=(
            "Pre-fill blank days with last fortnight's pattern (matched by weekday). "
            "Cells projected this way will be created as NEW entries on save."
        ),
    )
    return parser.parse_args(argv)


def main() -> None:
    args = parse_args()
    client = make_client()

    print("Fetching team members and plans…")
    team = fetch_team(client)
    if not team:
        die("no team members visible to your token. Are you set as manager of any team?")

    team_plans = [fetch_plan(client, member["id"]) for member in team]
    locations = fetch_locations(client)
    location_slugs = {loc["value"] for loc in locations}

    carry_forward_by_user: dict[int, dict[int, dict]] | None = None
    if args.carry_forward:
        rng = team_plans[0]["date_range"]
        prior_from, prior_to = previous_window(rng["start"], rng["end"])
        print(f"Carry-forward: fetching prior fortnight ({prior_from} → {prior_to})…")
        carry_forward_by_user = {}
        for member in team:
            prior_plan = fetch_plan_window(client, member["id"], prior_from, prior_to)
            carry_forward_by_user[member["id"]] = build_carry_forward_lookup(prior_plan)

    original_yaml = render(team_plans, locations, carry_forward_by_user=carry_forward_by_user)
    edited_yaml = open_in_editor(original_yaml)

    api_state = [to_diff_record(plan) for plan in team_plans]
    edited_data = parse_edited(edited_yaml)

    diff = diff_plans(api_state, edited_data)
    if not show_diff(diff):
        return

    if not confirm("Apply these changes?"):
        print("Aborted. No changes shipped.")
        return

    print("\nShipping changes…")
    push_changes(client, diff, location_slugs)
    print("\nDone.")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nAborted.")
        sys.exit(130)
