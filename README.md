<p align="center"><b>MUTE V2.+</b></p>

<img width="500" height="500" alt="471151478-96cd0bd4-fca9-4dc6-bbb3-a26a6aeddf3c" src="https://github.com/user-attachments/assets/307df13c-a011-4bf0-a47f-f691867293f7" />

MUTE – Multi-User Timed Encrypted ChatMUTE is a lightweight, browser-based chat system with strong end-to-end encryption, automatic 5-minute message expiry, and two modes: public ("Silver") and private ("Gold"). It runs on a PHP server and can be self-hosted if desired.Core FeaturesEnd-to-End Encryption: AES-256-GCM with client-side encryption/decryption (Web Crypto API)
Ephemeral Messages: AES-256-GCM with client-side encryption/decryption (Web Crypto API)
Dual Modes:Silver (Public): Shared rotating session key (5-minute windows), no user secrets required
Gold (Private): User-provided offline-shared secret → PBKDF2-derived key (high-entropy, quantum-resistant)

Key Rotation:Static master key (json_key.bin) rotates every 24 hours
Re-encrypts session/salt files automatically

No Database: Pure file-based storage (JSON + binary files in private/)
Security Headers: HTTPS enforcement, HSTS, CSP, X-Frame-Options, etc.
Obfuscation: Optional per-message salt + HKDF derivation for unique ciphertexts (Yet not implemented!)

Architecture Overview

Client (Browser)                  Server (PHP + Files)
───────────────                   ─────────────────────
│                                     │
│  • Input message                    │
│  • Derive per-message key (HKDF)    │
│  • Encrypt (AES-256-GCM)            │
│  • Send encrypted blob ───────────► │ Store in temp_talk_*.json (5-min TTL)
│                                     │
│ ◄────────────────────────────────── │ Fetch encrypted blobs (filtered)
│                                     │
│  • Decrypt (try all known keys)     │
│  • Display if valid                 │
│                                     │
└─────────────────────────────────────┘

Key Componentsprivate/ (never web-accessible)json_key.bin: Rotating 256-bit master key (rotates every 24h)
session_key.json: Time-window session keys for Silver mode
salt_key_mapping.json: Salt mappings for Gold mode
Logs: rotation, key creation, etc.

temp_talk_silver.json / temp_talk_gold.json
Ephemeral message pools (auto-cleaned to last 200 messages + 5-min expiry)

Security PostureClient-side E2EE — Server sees only encrypted blobs
Per-message uniqueness — HKDF + random salt/IV
Forward secrecy — Mild (per-window + per-message derivation)
Key compromise recovery — 24h master key rotation + re-encryption
Minimal footprint — No DB, no external deps beyond OpenSSL

Improvements Over Original DesignRemoved MySQL + complex DB schema
Eliminated gmp / deep DH-like chains
Simplified to single rotating master key + file-based storage
Easier deployment (works on shared hosting)
Same core security with less complexity

This version is production-ready, lightweight, and focused on real-world privacy in censored environments.Deploy, test, fork, improve — contributions welcome!


