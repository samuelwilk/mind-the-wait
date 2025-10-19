# Local TLS Setup Guide

This guide explains how to set up HTTPS/TLS for local development with mind-the-wait, including support for iOS simulator testing.

## Overview

The development environment uses **mkcert** to generate locally-trusted TLS certificates. This enables:
- ✅ HTTPS on `localhost`, `mind-the-wait.local`, and `mind-the-wait.test`
- ✅ Trusted certificates in macOS browsers (Safari, Chrome)
- ✅ Trusted certificates in iOS Simulator
- ✅ No browser security warnings during development
- ✅ Zero impact on production (dev-only configuration)

## Prerequisites

- **macOS** (mkcert works on other platforms but iOS simulator is macOS-only)
- **Homebrew** (for installing mkcert)
- **Docker** (nginx container must be running)
- **Admin/sudo access** (for trusting the root CA)

## Quick Start

### 1. Install mkcert

```bash
# Install mkcert via Homebrew
brew install mkcert

# Install the local Certificate Authority
mkcert -install
```

**What this does:**
- Installs mkcert CLI tool
- Creates a local Certificate Authority (CA) in `$(mkcert -CAROOT)`
- Adds the CA to macOS system trust store (requires sudo)
- Makes browsers and macOS trust certificates signed by this CA

### 2. Add Hostname to /etc/hosts (Optional)

If you want to use `mind-the-wait.local` instead of `localhost`:

```bash
# Edit /etc/hosts
sudo nano /etc/hosts

# Add this line:
127.0.0.1  mind-the-wait.local mind-the-wait.test
```

Save with `Ctrl+O`, exit with `Ctrl+X`.

### 3. Generate TLS Certificates

```bash
# From the project root
cd /path/to/mind-the-wait
make update-cert
```

**What this does:**
1. Creates `docker/dev/certs/` directory
2. Generates private key (`mind-the-wait.local.key`)
3. Generates certificate (`mind-the-wait.local.crt`)
4. Creates fullchain certificate (`mind-the-wait.local.fullchain.crt`)
   - Includes the site certificate + CA root
   - Required for iOS simulator and some clients

**Certificate covers these hostnames:**
- `mind-the-wait.local`
- `mind-the-wait.test`
- `localhost`
- `127.0.0.1`
- `::1` (IPv6 localhost)

### 4. Restart nginx

```bash
# Restart nginx to load new certificates
docker compose -f docker/compose.yaml --env-file .env.local restart nginx
```

### 5. Verify in Browser

Open https://localhost or https://mind-the-wait.local in Safari/Chrome.

**Expected:**
- ✅ No security warning
- ✅ Green padlock icon
- ✅ Certificate shows "Issued by: mkcert {username}"

**If you see warnings:**
- Check: `mkcert -install` was run and succeeded
- Check: `make update-cert` completed without errors
- Check: nginx restarted successfully
- Try: Close and reopen browser

## iOS Simulator Setup

To test the iOS app against your local development server, the simulator needs to trust the mkcert root CA.

### 1. Export the Root CA

```bash
# Get the CA certificate path
mkcert -CAROOT

# Example output: /Users/yourusername/Library/Application Support/mkcert
```

### 2. Install CA in iOS Simulator

#### Method A: Direct Install (Recommended)

```bash
# Boot a simulator first
open -a Simulator

# Install the root CA
xcrun simctl keychain booted add-root-cert \
  "$(mkcert -CAROOT)/rootCA.pem"
```

#### Method B: Manual Install

1. Boot simulator: `open -a Simulator`
2. Drag `rootCA.pem` from `$(mkcert -CAROOT)/` to simulator window
3. Settings → General → VPN & Device Management
4. Tap on mkcert CA profile → Install → Enter passcode → Install
5. Settings → General → About → Certificate Trust Settings
6. Enable trust for mkcert CA

### 3. Update iOS App Base URL

In your iOS app project (Xcode):

```swift
// For development builds
#if DEBUG
let baseURL = "https://localhost"
// OR
let baseURL = "https://mind-the-wait.local"
#else
let baseURL = "https://mindthewait.ca"
#endif
```

### 4. Verify from Simulator

```swift
// In iOS app, test an API call
URLSession.shared.dataTask(with: URL(string: "https://localhost/api/realtime")!) { data, response, error in
    if let error = error {
        print("❌ Error: \(error)")  // Should not see SSL errors
    } else {
        print("✅ Success")
    }
}.resume()
```

**Expected:**
- ✅ No SSL/TLS errors
- ✅ API responses load correctly

## Understanding the Setup

### Certificate Chain

```
[Site Certificate: mind-the-wait.local.crt]
        ↓ signed by
[Root CA: rootCA.pem from mkcert -CAROOT]
        ↓ trusted by
[macOS System Keychain / iOS Simulator Keychain]
```

### Fullchain vs Regular Certificate

- **mind-the-wait.local.crt**: Site certificate only
- **mind-the-wait.local.fullchain.crt**: Site certificate + Root CA
  - nginx uses this for better compatibility
  - iOS simulator needs the root CA in the chain

### Why Fullchain Matters

Some clients (including iOS) require the full certificate chain to be served by the server. The fullchain includes both:
1. Your site certificate (proves identity of mind-the-wait.local)
2. Root CA certificate (allows client to verify the chain)

nginx configuration:
```nginx
ssl_certificate     /etc/nginx/certs/mind-the-wait.local.fullchain.crt;
ssl_certificate_key /etc/nginx/certs/mind-the-wait.local.key;
```

## Production Isolation

**This TLS setup does NOT affect production:**

✅ **Isolated by design:**
- Certificates only in `docker/dev/certs/` (git-ignored)
- nginx config only in `docker/dev/nginx.conf` (mounted by compose.yaml)
- mkcert CA only on your development machine
- Production uses Let's Encrypt or AWS Certificate Manager

✅ **Safe practices:**
- Never commit certificates to git (already in `.gitignore`)
- Never use mkcert certificates in production
- Production nginx config is separate (managed by deployment tooling)

## Troubleshooting

### "Certificate not trusted" in browser

**Symptoms:**
- "Your connection is not private" warning
- "NET::ERR_CERT_AUTHORITY_INVALID"

**Fix:**
```bash
# Re-install CA
mkcert -install

# Regenerate certificates
make update-cert

# Restart nginx
docker compose -f docker/compose.yaml --env-file .env.local restart nginx

# Close and reopen browser
```

### "SSL Error" in iOS Simulator

**Symptoms:**
- `NSURLErrorDomain Code=-1200` (SSL error)
- "The certificate for this server is invalid"

**Fix:**
```bash
# Verify simulator is running
open -a Simulator

# Install root CA in simulator
xcrun simctl keychain booted add-root-cert \
  "$(mkcert -CAROOT)/rootCA.pem"

# Verify installation
xcrun simctl keychain booted list
```

### "Certificate expired" after months

**Cause:** mkcert certificates are valid for 825 days, but CA root may need renewal

**Fix:**
```bash
# Regenerate CA and certificates
mkcert -uninstall
mkcert -install
make update-cert
docker compose -f docker/compose.yaml --env-file .env.local restart nginx

# Re-install in iOS simulator
xcrun simctl keychain booted add-root-cert \
  "$(mkcert -CAROOT)/rootCA.pem"
```

### nginx won't start after certificate update

**Symptoms:**
- `docker compose logs nginx` shows SSL errors
- nginx exits immediately

**Fix:**
```bash
# Check certificate files exist
ls -la docker/dev/certs/

# Should show:
# - mind-the-wait.local.key
# - mind-the-wait.local.crt
# - mind-the-wait.local.fullchain.crt

# If missing, regenerate
make update-cert

# Check nginx config syntax
docker compose -f docker/compose.yaml --env-file .env.local \
  exec nginx nginx -t

# Restart
docker compose -f docker/compose.yaml --env-file .env.local restart nginx
```

### /etc/hosts entries not working

**Symptoms:**
- `ping mind-the-wait.local` doesn't resolve
- Browser can't find site

**Fix:**
```bash
# Verify /etc/hosts has entry
cat /etc/hosts | grep mind-the-wait

# Should show:
# 127.0.0.1  mind-the-wait.local mind-the-wait.test

# If missing, add it
sudo nano /etc/hosts
# Add: 127.0.0.1  mind-the-wait.local mind-the-wait.test
# Save and exit

# Flush DNS cache
sudo dscacheutil -flushcache
sudo killall -HUP mDNSResponder

# Test resolution
ping mind-the-wait.local
# Should resolve to 127.0.0.1
```

## Advanced: Using with Physical iOS Device

For testing on a physical iPhone/iPad connected to your Mac:

### 1. Make your Mac accessible on local network

```bash
# Find your Mac's local IP
ipconfig getifaddr en0
# Example: 192.168.1.100
```

### 2. Generate certificate with your Mac's IP

```bash
# Edit Makefile's update-cert target to include your IP
mkcert -key-file docker/dev/certs/mind-the-wait.local.key \
  -cert-file docker/dev/certs/mind-the-wait.local.crt \
  mind-the-wait.local mind-the-wait.test localhost \
  127.0.0.1 ::1 192.168.1.100

# Create fullchain
cat docker/dev/certs/mind-the-wait.local.crt \
  "$(mkcert -CAROOT)/rootCA.pem" \
  > docker/dev/certs/mind-the-wait.local.fullchain.crt

# Restart nginx
docker compose -f docker/compose.yaml --env-file .env.local restart nginx
```

### 3. Install CA on physical device

1. Email yourself `rootCA.pem` from `$(mkcert -CAROOT)/`
2. Open email on iPhone → Tap `rootCA.pem` → Install Profile
3. Settings → General → VPN & Device Management → Install
4. Settings → General → About → Certificate Trust Settings → Enable trust

### 4. Update iOS app to use your Mac's IP

```swift
#if DEBUG
let baseURL = "https://192.168.1.100"
#else
let baseURL = "https://mindthewait.ca"
#endif
```

**Note:** Your Mac's firewall must allow incoming connections on port 443.

## Certificate Renewal

mkcert certificates are valid for **825 days** (~2.3 years). To renew:

```bash
# Check expiry
openssl x509 -in docker/dev/certs/mind-the-wait.local.crt -noout -dates

# Regenerate certificates (CA remains the same)
make update-cert

# Restart nginx
docker compose -f docker/compose.yaml --env-file .env.local restart nginx
```

The root CA itself rarely needs renewal. If it does:

```bash
mkcert -uninstall
rm -rf "$(mkcert -CAROOT)"
mkcert -install
make update-cert

# Re-install in iOS simulator
xcrun simctl keychain booted add-root-cert \
  "$(mkcert -CAROOT)/rootCA.pem"
```

## Security Considerations

### Development Only

⚠️ **NEVER use mkcert certificates in production:**
- mkcert is for local development only
- Certificates are only trusted on your machine
- Anyone with your CA private key can issue trusted certificates
- Use Let's Encrypt or AWS Certificate Manager for production

### Keep CA Private

🔒 **Protect your mkcert CA:**
- Location: `$(mkcert -CAROOT)/`
- Contains: `rootCA.pem` (public) and `rootCA-key.pem` (private)
- **DO NOT** share `rootCA-key.pem` with anyone
- **DO NOT** commit CA to version control
- If compromised: `mkcert -uninstall && mkcert -install`

### Team Development

For team collaboration:
- ✅ Each developer runs `mkcert -install` on their machine
- ✅ Each developer runs `make update-cert`
- ❌ **DO NOT** share CA certificates between developers
- ❌ **DO NOT** commit `docker/dev/certs/` to git

## References

- [mkcert GitHub](https://github.com/FiloSottile/mkcert)
- [nginx SSL Configuration](https://nginx.org/en/docs/http/configuring_https_servers.html)
- [iOS Simulator Keychain](https://developer.apple.com/documentation/security/keychain_services)
- [Apple Transport Security](https://developer.apple.com/documentation/security/preventing_insecure_network_connections)

## Quick Reference

```bash
# Install mkcert
brew install mkcert && mkcert -install

# Generate certificates
make update-cert

# Restart nginx
docker compose -f docker/compose.yaml --env-file .env.local restart nginx

# Install in iOS simulator
xcrun simctl keychain booted add-root-cert "$(mkcert -CAROOT)/rootCA.pem"

# Verify in browser
open https://localhost

# Check certificate details
openssl x509 -in docker/dev/certs/mind-the-wait.local.crt -noout -text
```
