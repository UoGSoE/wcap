package api

// AvailabilityStatus mirrors App\Enums\AvailabilityStatus in the Laravel app.
type AvailabilityStatus int

const (
	NotAvailable AvailabilityStatus = 0
	Remote       AvailabilityStatus = 1
	Onsite       AvailabilityStatus = 2
)

func (s AvailabilityStatus) Label() string {
	switch s {
	case Onsite:
		return "Onsite"
	case Remote:
		return "Remote"
	case NotAvailable:
		return "Not Available"
	}
	return "Unknown"
}

// Code returns a single-letter code matching the Laravel enum's code() method.
func (s AvailabilityStatus) Code() string {
	switch s {
	case Onsite:
		return "O"
	case Remote:
		return "R"
	}
	return "N"
}

// IsAvailable reports whether this status counts as "available somewhere".
func (s AvailabilityStatus) IsAvailable() bool {
	return s > 0
}

// User is the minimal user identity returned alongside plan responses.
type User struct {
	ID   int    `json:"id"`
	Name string `json:"name"`
}

// AuthUser is the shape returned by GET /api/user (the Sanctum endpoint).
// The Laravel route returns the raw User model, so we get forenames + surname
// rather than the `name` shape used by the plan endpoints. We accept either.
type AuthUser struct {
	ID        int    `json:"id"`
	FullName  string `json:"full_name,omitempty"`
	Name      string `json:"name,omitempty"`
	Forenames string `json:"forenames,omitempty"`
	Surname   string `json:"surname,omitempty"`
	Username  string `json:"username,omitempty"`
	Email     string `json:"email,omitempty"`
}

// DisplayName returns the most human-readable name available.
func (u AuthUser) DisplayName() string {
	if u.FullName != "" {
		return u.FullName
	}
	if u.Name != "" {
		return u.Name
	}
	if u.Forenames != "" || u.Surname != "" {
		switch {
		case u.Forenames == "":
			return u.Surname
		case u.Surname == "":
			return u.Forenames
		default:
			return u.Forenames + " " + u.Surname
		}
	}
	if u.Username != "" {
		return u.Username
	}
	return u.Email
}

// Entry represents a single plan entry, both inbound and outbound.
// When sending an upsert, only ID, EntryDate, Location, Note, and
// AvailabilityStatus are read by the server.
type Entry struct {
	ID                      *int               `json:"id,omitempty"`
	EntryDate               string             `json:"entry_date"`
	Location                string             `json:"location,omitempty"` // slug
	LocationLabel           string             `json:"location_label,omitempty"`
	Note                    string             `json:"note,omitempty"`
	AvailabilityStatus      AvailabilityStatus `json:"availability_status"`
	AvailabilityStatusLabel string             `json:"availability_status_label,omitempty"`
	IsHoliday               bool               `json:"is_holiday,omitempty"`
	Category                string             `json:"category,omitempty"`
	CategoryLabel           string             `json:"category_label,omitempty"`
	CreatedByManager        bool               `json:"created_by_manager,omitempty"`
}

// PlanResponse is the shape of /api/v1/plan and the manager equivalents.
type PlanResponse struct {
	User      User      `json:"user"`
	DateRange DateRange `json:"date_range"`
	Entries   []Entry   `json:"entries"`
}

// DateRange is the inclusive YYYY-MM-DD window covered by a plan response.
type DateRange struct {
	Start string `json:"start"`
	End   string `json:"end"`
}

// TeamMember is the minimal info needed to render the left pane.
type TeamMember struct {
	ID    int    `json:"id"`
	Name  string `json:"name"`
	Email string `json:"email"`
}

// Location is reference data for the location picker.
type Location struct {
	Value      string `json:"value"` // slug — what the API wants
	Label      string `json:"label"`
	ShortLabel string `json:"short_label"`
}

// FillDefaultsResult is the response from the fill-defaults endpoint.
// skipped_reason is "no_defaults" when the target has no usable defaults;
// absent otherwise (filled_days 0 with no reason just means nothing was empty).
type FillDefaultsResult struct {
	FilledDays    int    `json:"filled_days"`
	SkippedReason string `json:"skipped_reason,omitempty"`
}

// fillDefaultsPayload is the request body for the fill-defaults endpoint.
type fillDefaultsPayload struct {
	WeekStart string `json:"week_start"`
	OnlyDate  string `json:"only_date,omitempty"`
}

// upsertPayload is the request body for both upsert endpoints.
type upsertPayload struct {
	Entries []upsertEntry `json:"entries"`
}

type upsertEntry struct {
	ID                 *int   `json:"id,omitempty"`
	EntryDate          string `json:"entry_date"`
	Location           string `json:"location,omitempty"`
	Note               string `json:"note,omitempty"`
	AvailabilityStatus int    `json:"availability_status"`
}

func toUpsertEntries(entries []Entry) []upsertEntry {
	out := make([]upsertEntry, len(entries))
	for i, e := range entries {
		out[i] = upsertEntry{
			ID:                 e.ID,
			EntryDate:          e.EntryDate,
			Location:           e.Location,
			Note:               e.Note,
			AvailabilityStatus: int(e.AvailabilityStatus),
		}
	}
	return out
}
