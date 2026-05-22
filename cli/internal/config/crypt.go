package config

import (
	"crypto/rand"
	"encoding/base64"
	"errors"
	"fmt"
	"strings"

	"golang.org/x/crypto/argon2"
	"golang.org/x/crypto/chacha20poly1305"
)

// Crypto choices, locked behind the "v1:" prefix so a future "v2:" can change
// any of them without breaking existing files:
//
//   KDF:      Argon2id (time=1, memory=32MB, threads=2, keyLen=32)
//   AEAD:     ChaCha20-Poly1305
//   Salt:     16 random bytes per encryption
//   Nonce:    12 random bytes per encryption (chacha20poly1305.NonceSize)
//
// On-disk format: "v1:" || base64( salt || nonce || ciphertext+tag )
const (
	cryptVersion = "v1:"

	saltLen = 16
	keyLen  = chacha20poly1305.KeySize // 32

	argonTime    = 1
	argonMemory  = 32 * 1024 // KB → 32 MiB
	argonThreads = 2
)

// ErrBadPassphrase is returned when decryption fails because the passphrase
// is wrong (or the blob is corrupt — indistinguishable by design).
var ErrBadPassphrase = errors.New("config: incorrect passphrase or corrupted token")

// EncryptToken seals plaintext with a key derived from passphrase. Output is
// safe to embed in YAML as a single line.
func EncryptToken(plaintext, passphrase string) (string, error) {
	if passphrase == "" {
		return "", errors.New("config: empty passphrase")
	}

	salt := make([]byte, saltLen)
	if _, err := rand.Read(salt); err != nil {
		return "", fmt.Errorf("config: read random salt: %w", err)
	}

	key := argon2.IDKey([]byte(passphrase), salt, argonTime, argonMemory, argonThreads, keyLen)

	aead, err := chacha20poly1305.New(key)
	if err != nil {
		return "", fmt.Errorf("config: build cipher: %w", err)
	}

	nonce := make([]byte, aead.NonceSize())
	if _, err := rand.Read(nonce); err != nil {
		return "", fmt.Errorf("config: read random nonce: %w", err)
	}

	ciphertext := aead.Seal(nil, nonce, []byte(plaintext), nil)

	blob := make([]byte, 0, len(salt)+len(nonce)+len(ciphertext))
	blob = append(blob, salt...)
	blob = append(blob, nonce...)
	blob = append(blob, ciphertext...)

	return cryptVersion + base64.StdEncoding.EncodeToString(blob), nil
}

// DecryptToken reverses EncryptToken. Returns ErrBadPassphrase on any
// failure that's plausibly user-driven (wrong passphrase, corrupt blob).
func DecryptToken(encoded, passphrase string) (string, error) {
	if passphrase == "" {
		return "", errors.New("config: empty passphrase")
	}
	if !strings.HasPrefix(encoded, cryptVersion) {
		return "", fmt.Errorf("config: unrecognised encrypted-token format")
	}

	blob, err := base64.StdEncoding.DecodeString(strings.TrimPrefix(encoded, cryptVersion))
	if err != nil {
		return "", fmt.Errorf("config: decode encrypted token: %w", err)
	}

	if len(blob) < saltLen+chacha20poly1305.NonceSize+chacha20poly1305.Overhead {
		return "", ErrBadPassphrase
	}

	salt := blob[:saltLen]
	nonce := blob[saltLen : saltLen+chacha20poly1305.NonceSize]
	ciphertext := blob[saltLen+chacha20poly1305.NonceSize:]

	key := argon2.IDKey([]byte(passphrase), salt, argonTime, argonMemory, argonThreads, keyLen)

	aead, err := chacha20poly1305.New(key)
	if err != nil {
		return "", fmt.Errorf("config: build cipher: %w", err)
	}

	plaintext, err := aead.Open(nil, nonce, ciphertext, nil)
	if err != nil {
		return "", ErrBadPassphrase
	}

	return string(plaintext), nil
}

// IsEncrypted reports whether a token value is an encrypted blob.
func IsEncrypted(s string) bool {
	return strings.HasPrefix(s, cryptVersion)
}
