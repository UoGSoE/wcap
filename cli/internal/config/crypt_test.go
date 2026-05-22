package config

import (
	"errors"
	"strings"
	"testing"
)

func TestEncryptDecryptRoundTrip(t *testing.T) {
	enc, err := EncryptToken("super-secret-token", "correct horse battery staple")
	if err != nil {
		t.Fatal(err)
	}
	if !IsEncrypted(enc) {
		t.Errorf("encrypted output should be tagged: %q", enc)
	}
	if !strings.HasPrefix(enc, "v1:") {
		t.Errorf("expected v1 prefix, got %q", enc[:5])
	}

	got, err := DecryptToken(enc, "correct horse battery staple")
	if err != nil {
		t.Fatal(err)
	}
	if got != "super-secret-token" {
		t.Errorf("round-trip mismatch: %q", got)
	}
}

func TestEncryptionUsesFreshSaltAndNonce(t *testing.T) {
	a, err := EncryptToken("token", "passphrase")
	if err != nil {
		t.Fatal(err)
	}
	b, err := EncryptToken("token", "passphrase")
	if err != nil {
		t.Fatal(err)
	}
	if a == b {
		t.Errorf("two encryptions of the same input should differ (salt/nonce should be random)")
	}
}

func TestDecryptWrongPassphraseIsTypedError(t *testing.T) {
	enc, err := EncryptToken("token", "right-one")
	if err != nil {
		t.Fatal(err)
	}
	_, err = DecryptToken(enc, "wrong-one")
	if !errors.Is(err, ErrBadPassphrase) {
		t.Fatalf("expected ErrBadPassphrase, got %v", err)
	}
}

func TestDecryptCorruptedBlobIsTypedError(t *testing.T) {
	enc, err := EncryptToken("token", "passphrase")
	if err != nil {
		t.Fatal(err)
	}
	// Flip a byte deep in the ciphertext.
	bad := enc[:len(enc)-3] + "AAA"
	_, err = DecryptToken(bad, "passphrase")
	if !errors.Is(err, ErrBadPassphrase) {
		t.Fatalf("expected ErrBadPassphrase, got %v", err)
	}
}

func TestEmptyPassphraseRejected(t *testing.T) {
	if _, err := EncryptToken("token", ""); err == nil {
		t.Error("encrypt with empty passphrase should fail")
	}
	if _, err := DecryptToken("v1:abc", ""); err == nil {
		t.Error("decrypt with empty passphrase should fail")
	}
}

func TestDecryptUnknownPrefixRejected(t *testing.T) {
	if _, err := DecryptToken("v99:not-real", "passphrase"); err == nil {
		t.Error("decrypt with unknown version should fail")
	}
}

func TestIsEncryptedDistinguishesPlaintext(t *testing.T) {
	if IsEncrypted("plain-old-token") {
		t.Error("plain token shouldn't look encrypted")
	}
	enc, _ := EncryptToken("x", "p")
	if !IsEncrypted(enc) {
		t.Error("encrypted token should be recognised")
	}
}
