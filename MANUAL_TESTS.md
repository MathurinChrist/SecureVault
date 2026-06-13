# SecureVault — Manual Test Checklist

**Base URL:** `http://localhost:8080`  
**Mailpit (email preview):** `http://localhost:8025`

Start the stack before testing:
```bash
docker compose up -d
```

Legend: `[ ]` to test · `[x]` done · `[~]` partial · `[!]` bug found

---

## 1. Public pages

| # | Scenario | Steps | Expected |
|---|----------|-------|----------|
| 1.1 | Homepage loads | GET `/` | 200, marketing content visible |
| 1.2 | Features page loads | GET `/features` | 200 |
| 1.3 | Security page loads | GET `/security` | 200 |
| 1.4 | Pricing page loads | GET `/pricing` | 200 |

- [ ] 1.1
- [ ] 1.2
- [ ] 1.3
- [ ] 1.4

---

## 2. Authentication — Login / Logout

| # | Scenario | Steps | Expected |
|---|----------|-------|----------|
| 2.1 | Login page renders | GET `/login` | Email, password inputs + "Se connecter" button |
| 2.2 | Unauthenticated access to dashboard | GET `/dashboard` (not logged in) | Redirect → `/login` |
| 2.3 | Unauthenticated access to vaults | GET `/vaults` (not logged in) | Redirect → `/login` |
| 2.4 | Login with wrong password | Submit wrong password | Stays on `/login`, error message shown |
| 2.5 | Login with correct credentials | Submit valid email + password | Redirect → `/dashboard` |
| 2.6 | Logout | Click logout link | Redirect → `/` or `/login`, session cleared |
| 2.7 | Brute-force lockout alert | Fail login 5+ times with same email | Security alert created (visible in DB or alert UI) |

- [ ] 2.1
- [ ] 2.2
- [ ] 2.3
- [ ] 2.4
- [ ] 2.5
- [ ] 2.6
- [ ] 2.7

---

## 3. Registration

| # | Scenario | Steps | Expected |
|---|----------|-------|----------|
| 3.1 | Registration page loads | GET `/register` | Form with firstName, lastName, email, plainPassword, agreeTerms |
| 3.2 | Successful registration | Fill all fields, password ≥ 6 chars, accept terms, submit | Redirect → `/login` |
| 3.3 | Password too short | Submit with password < 6 chars | Stays on `/register`, validation error shown |
| 3.4 | Missing required fields | Submit empty form | Validation errors for each missing field |
| 3.5 | Duplicate email | Register twice with same email | Error about email already in use |

- [ ] 3.1
- [ ] 3.2
- [ ] 3.3
- [ ] 3.4
- [ ] 3.5

---

## 4. Dashboard

| # | Scenario | Steps | Expected |
|---|----------|-------|----------|
| 4.1 | Dashboard loads | Login → GET `/dashboard` | 200, user name visible |
| 4.2 | Auto-vault creation on first login | Register new user → visit dashboard | Default vault + demo password auto-created |
| 4.3 | Password count widget | Add passwords → revisit dashboard | Count increments correctly |

- [ ] 4.1
- [ ] 4.2
- [ ] 4.3

---

## 5. Vaults

| # | Scenario | Steps | Expected |
|---|----------|-------|----------|
| 5.1 | Vault list page | GET `/vaults` | Lists existing vaults, create button visible |
| 5.2 | Create vault (modal form) | Click "Nouveau coffre" or "Créer", fill name, submit | Redirect back, new vault appears in list |
| 5.3 | Vault detail page | Click on a vault | Shows vault name, passwords list |
| 5.4 | Access another user's vault | Manually navigate to `/vaults/{other_id}` | 403 Forbidden |
| 5.5 | Archive vault | Open archive form in vault list, submit | Vault status changes |

- [ ] 5.1
- [ ] 5.2
- [ ] 5.3
- [ ] 5.4
- [ ] 5.5

---

## 6. Passwords (in vault)

| # | Scenario | Steps | Expected |
|---|----------|-------|----------|
| 6.1 | Empty password list | Open a new vault | Empty list or "no passwords" message |
| 6.2 | Add password | Fill title + password, submit | Entry appears in list |
| 6.3 | View decrypted password | Click reveal/show on an entry | Plain password displayed |
| 6.4 | Delete password | Delete an entry | Entry removed from list |
| 6.5 | Add without title | Submit password form with no title | Validation error |

- [ ] 6.1
- [ ] 6.2
- [ ] 6.3
- [ ] 6.4
- [ ] 6.5

---

## 7. Profile

| # | Scenario | Steps | Expected |
|---|----------|-------|----------|
| 7.1 | Profile page loads | GET `/profile` | Forms for profile info and password change visible |
| 7.2 | Update first/last name | Change name, click "Sauvegarder les modifications" | Redirect back, new name reflected |
| 7.3 | Change password | Fill new password fields, submit | Redirect back, new password works at login |
| 7.4 | Upload profile image | Select a JPG/PNG ≤ 2 MB, submit | Image updated in UI |
| 7.5 | Upload oversized image | Select a file > 2 MB | Validation error |
| 7.6 | Upload wrong file type | Select a PDF | Validation error about mime type |

- [ ] 7.1
- [ ] 7.2
- [ ] 7.3
- [ ] 7.4
- [ ] 7.5
- [ ] 7.6

---

## 8. Vault sharing

| # | Scenario | Steps | Expected |
|---|----------|-------|----------|
| 8.1 | Shares index page | GET `/shares` | 200, lists vaults shared with me |
| 8.2 | Vault shares page (owner) | GET `/vaults/{id}/shares` as owner | 200, invite form visible |
| 8.3 | Vault shares page (non-owner) | GET `/vaults/{id}/shares` as other user | 403 Forbidden |
| 8.4 | Invite unknown email | Submit invite form with non-existent email | Redirect back, error flash shown |
| 8.5 | Invite known user (READ) | Submit invite form with existing user's email + READ permission | Redirect, share created |
| 8.6 | Invited user can view | Log in as invited user → access shared vault | View allowed, no 403 |
| 8.7 | Invited user cannot edit (READ) | Try editing vault as READ-only invitee | 403 Forbidden |
| 8.8 | Admin invitee can share | Share vault with ADMIN permission → invited user opens shares page | Invite form visible |

- [ ] 8.1
- [ ] 8.2
- [ ] 8.3
- [ ] 8.4
- [ ] 8.5
- [ ] 8.6
- [ ] 8.7
- [ ] 8.8

---

## 9. REST API (use curl or a REST client)

### Auth
```bash
# 9.1 — Login without credentials → 401
curl -s -o /dev/null -w "%{http_code}" -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" -d '{}'

# 9.2 — Login with credentials → 200 + token
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"you@example.com","password":"YourPass123!"}' | jq -r '.token')
echo $TOKEN
```

- [ ] 9.1 — expects `401`
- [ ] 9.2 — expects `200` with `token` field

### Vaults API
```bash
# 9.3 — List vaults (authenticated) → 200 JSON array
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8080/api/v1/vaults

# 9.4 — Create vault → 201 with id
VAULT_ID=$(curl -s -X POST http://localhost:8080/api/v1/vaults \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"API Vault","description":"test"}' | jq -r '.id')
echo $VAULT_ID

# 9.5 — Create vault without name → 422
curl -s -o /dev/null -w "%{http_code}" -X POST http://localhost:8080/api/v1/vaults \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"description":"no name"}'

# 9.6 — Delete vault → 204
curl -s -o /dev/null -w "%{http_code}" -X DELETE \
  http://localhost:8080/api/v1/vaults/$VAULT_ID \
  -H "Authorization: Bearer $TOKEN"
```

- [ ] 9.3 — expects `200`, array
- [ ] 9.4 — expects `201`, has `id`
- [ ] 9.5 — expects `422`
- [ ] 9.6 — expects `204`

### Passwords API
```bash
# Re-create a vault first
VAULT_ID=$(curl -s -X POST http://localhost:8080/api/v1/vaults \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"PW Vault"}' | jq -r '.id')

# 9.7 — List passwords (empty) → 200 []
curl -s -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/api/v1/vaults/$VAULT_ID/passwords

# 9.8 — Create password → 201, plain password NOT in response
PW_ID=$(curl -s -X POST http://localhost:8080/api/v1/vaults/$VAULT_ID/passwords \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"title":"GitHub","username":"alice","password":"S3cr3t!","url":"https://github.com"}' \
  | tee /dev/stderr | jq -r '.id')

# 9.9 — Show password → decrypted value returned
curl -s -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/api/v1/vaults/$VAULT_ID/passwords/$PW_ID

# 9.10 — Create without title → 422
curl -s -o /dev/null -w "%{http_code}" -X POST \
  http://localhost:8080/api/v1/vaults/$VAULT_ID/passwords \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"password":"bar"}'

# 9.11 — Delete password → 204, then GET → 404
curl -s -o /dev/null -w "%{http_code}" -X DELETE \
  http://localhost:8080/api/v1/vaults/$VAULT_ID/passwords/$PW_ID \
  -H "Authorization: Bearer $TOKEN"

# 9.12 — Access another user's vault passwords → 403
# (get a second token for a different account first)
curl -s -o /dev/null -w "%{http_code}" \
  -H "Authorization: Bearer $TOKEN2" \
  http://localhost:8080/api/v1/vaults/$VAULT_ID/passwords

# 9.13 — No auth → 401
curl -s -o /dev/null -w "%{http_code}" \
  http://localhost:8080/api/v1/vaults/1/passwords
```

- [ ] 9.7 — expects `200`, empty array
- [ ] 9.8 — expects `201`, has `id` and `title`, no `password` field in response body
- [ ] 9.9 — expects `200`, `password` field equals `S3cr3t!`
- [ ] 9.10 — expects `422`
- [ ] 9.11 — expects `204` then `404`
- [ ] 9.12 — expects `403`
- [ ] 9.13 — expects `401`

---

## 10. Security edge cases

| # | Scenario | Steps | Expected |
|---|----------|-------|----------|
| 10.1 | CSRF protection on forms | Tamper CSRF token in any form, submit | 419 / CSRF error, request rejected |
| 10.2 | Encryption at rest | Insert password via API, inspect `password_entry` table | `password` column is ciphertext, not plaintext |
| 10.3 | Encryption round-trip | Create password with known value, read it back via API | Decrypted value matches original |
| 10.4 | Different ciphertext each time | Insert two passwords with same plaintext | Ciphertexts differ (random IV) |
| 10.5 | Password strength widget | Visit password generator / profile, enter various passwords | Strength label updates (Très faible → Très fort) |

- [ ] 10.1
- [ ] 10.2  `docker compose exec database psql -U app app_test -c "SELECT password FROM password_entry LIMIT 5;"`
- [ ] 10.3
- [ ] 10.4
- [ ] 10.5

---

## Run automated tests

```bash
# All non-E2E (fast, ~3 s)
docker compose exec app php bin/phpunit tests/Controller/ tests/Service/ tests/Security/

# E2E only (needs Chrome, ~30 s)
docker compose exec app php bin/phpunit tests/E2E/

# Full suite
docker compose exec app php bin/phpunit
```

Expected baseline: **97 tests, 395 assertions, 0 failures**.
