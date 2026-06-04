# MisPunch Rules Report — Kiran Industries

**Version:** June 2026 (shift-session engine)  
**Routes:** `/mispunch`, `/reports/mispunch-download-24h`, `/reports/mispunch-form-pdf`  
**Main files:** `app/Helpers/helper.php`, `BiometricAttendanceSyncController.php`, `AttendanceProcessor.php`

---

## 1. Overview

MisPunch (status **MIS**) tab hota hai jab employee ke biometric punches **IN/OUT pairs** complete nahi hain.  
System ESSL logs ko **time order** mein padhta hai, **shift session day** par group karta hai, phir **MIS** ya **Present** decide karta hai.

**Important:** Purane records galat ho sakte hain jab tak **re-sync** na ho.

### Core engine rule

System uses:

- **Chronological sequence** (pehla IN → agla OUT / IN)
- **Shift session day** (`attendance_date`)

**NOT** calendar-date-only pairing.

---

## 2. Sync pipeline (step-by-step)

| Step | Function | Kya hota hai |
|------|----------|--------------|
| 1 | `EsslLog` fetch | Employee ke punches date range se (±1 day night shift ke liye) |
| 2 | `deduplicateRawLogs()` | Same minute + same direction duplicate hataata hai |
| 3 | `groupEmployeePunchesByAttendanceDate()` | Chronological IN→OUT pairing + shift day bucket |
| 4 | `analyzePunchSequence()` | `log_details`, work minutes, MIS flag |
| 5 | `shouldDeferOpenInMispunch()` | Open IN → temporary pending (session / next punch) |
| 6 | `saveAttendanceRecord()` | DB: `attendance_date`, `status`, `log_details` |

---

## 3. Core pairing rules (sab employees)

### Rule P1 — IN → next OUT = one pair

- Chronological order mein pehla **IN** agla **OUT** se band hota hai (agle calendar din subah ka OUT bhi chalega).
- Dono punches **IN wale shift day** par save hote hain.

**Example (night):**  
`2 Jun 21:00 IN` + `3 Jun 01:00 OUT` → dono **2 Jun** `attendance_date` par.

### Rule P2 — IN → next IN (bina OUT) = mispunch

- Agla punch **IN** hai (OUT nahi) → pehla IN **open** rehta hai → **MIS**.
- Naya IN nayi cycle start karta hai.

**Example:**  
`3 Jun 10:23 IN` → `4 Jun 09:44 IN` (beech mein OUT nahi) → **3 Jun = MIS** (missing OUT).

### Rule P3 — OUT bina pehle IN = mispunch

- Koi open IN nahi → orphan **OUT** → **missing IN** → MIS.

**Example:**  
`09:17 IN, 19:57 OUT, 19:58 OUT` → doosra OUT ke liye IN missing.

### Rule P4 — Multiple valid pairs same shift day

Ek shift day par **multiple valid pairs allowed hain, ONLY IF every IN has a matching OUT.**

Beech ka gap (break) count nahi hota — sirf complete pair ka IN→OUT time.

**Example (night session):**  
`21:00 IN, 01:00 OUT, 03:00 IN, 06:00 OUT` → **2 pairs**, **not MIS** (agar sab close hon).

---

## 4. Shift session day (night / multi-shift)

**Dynamic:** `employee.shift.slots` se `start_time` / `end_time` (fixed 20:00 nahi). Sirf **2h grace** (`dayStart - 120 min`) code mein fixed hai.

Calendar date se alag — **shift day** = session start ki date.

### Important — session-based pairing

System punches ko **calendar-date se pair nahi karta.**

System:

- chronological sequence
- shift session day

ke basis par pairing karta hai.

Isliye:

```text
21:00 IN
01:00 OUT
03:00 IN
06:00 OUT
```

same shift session par valid rehta hai.

### 4.1 Shift session window

| Shift type | Session start | Session end (open IN defer) |
|------------|---------------|-----------------------------|
| **Multi** (Day + Night) | Day slot start − 2h grace (e.g. 06:00) | Agle din **night start** (slot se dynamic) |
| **Night only** | Night start (slot se dynamic) | Agle din **night start** |
| **Day only** | Calendar day start | Open IN defer **only for current running day** |

Functions: `getShiftSessionStart()`, `getShiftSessionEnd()`, `resolveShiftAttendanceDate()`

**Day-only actual behavior:**

- Aaj open IN → pending (NOT MIS yet)
- Agle din → MIS (NOT true 24h session logic)

### 4.2 Multi-shift — punch kis din par jayega

Resolution via `resolveShiftAttendanceDate()` / `assignPunchToAttendanceDate()` — **not hardcoded time-only rules.**

| Situation | Shift day |
|-----------|-----------|
| Subah OUT (night band close) | Previous shift day |
| Subah IN (day grace / day login) | Same calendar day |
| Day slot IN/OUT | Same calendar day |
| Unpaired / orphan OUT without open IN | **Current resolved shift day** |
| ≥ night start IN | Same calendar day (night open) |

### 4.3 Night-only shift

| Punch | Shift day |
|-------|-----------|
| ≥ night start IN | Calendar date of IN |
| Subah IN (day login window) | Same calendar day |
| Subah OUT | Previous shift day |

---

## 5. Open IN — kab MIS, kab nahi (defer)

Open IN = **temporary pending state** until:

- **session expiry**, OR
- **next chronological punch** (ESSL)

### Rule D1 — Session abhi khatam nahi (extended defer)

- Last punch **IN** hai, OUT nahi.
- Abhi time **&lt; getShiftSessionEnd()** → **MIS nahi** (pending).
- Example: `2 Jun 21:00 IN`, aaj `3 Jun 15:00` → session end `3 Jun 20:00` → defer.

**NOTE — Defer scope:**

Extended session defer applies **ONLY** for:

- Night shift (`employeeShiftSpansMidnight()`)
- Multi shift (`employeeIsMultiShift()`)
- Cross-midnight shift slots

**Normal day-shift employees** use strict same-day validation (see D3).

### Rule D2 — Open IN ke baad koi punch aa chuka

- ESSL mein open IN ke **baad** koi bhi punch mila → defer **band** → decision ho chuka.
- Agla punch **IN** hai → **MIS** (Rule P2).
- Agla punch **OUT** hai → sync pair karega; PDF may suggest OUT within same shift session only.

### Rule D3 — Day-only shift

- Sirf **aaj** (`attendance_date === today`) par open IN defer.
- Kal ki date par open IN → **MIS**.

---

## 6. Technical safety rule

Before applying extended defer logic, system checks:

```text
employeeShiftSpansMidnight()
OR
employeeIsMultiShift()
```

If **neither** is true → day-only defer (today only).  
This prevents normal-shift employees from bypassing MIS validation accidentally.

**Code:** `shouldDeferOpenInMispunch()` in `helper.php`

---

## 7. Kab status = MIS (final)

| Condition | MIS? |
|-----------|------|
| IN count ≠ OUT count | Yes |
| IN ke baad IN (unclosed) | Yes |
| OUT without IN | Yes |
| Last pair: IN without OUT + defer nahi | Yes |
| Sab pairs complete, counts equal | No → P / HD / A (duty rules) |

### VALID

```text
IN OUT
IN OUT IN OUT
Night IN → Morning OUT (same shift day)
```

### MIS

```text
IN IN
OUT OUT (orphan)
OUT only
Open IN after session expiry
Open IN + next punch is IN
```

**Sync:** `analyzePunchSequence()` + `shouldDeferOpenInMispunch()`  
**Save:** `AttendanceProcessor` — defer par `is_mis_punch = false`

---

## 8. Work minutes (`total_minutes`)

- Sirf **complete IN→OUT pairs** count.
- Pair ke beech ka gap **include nahi**.
- Night cross-midnight: OUT clock &lt; IN clock → +24 hours.

Function: `sumWorkMinutesFromLogDetails()`

---

## 9. `log_details` format

```text
08:27 IN, 19:02 OUT, 21:20 IN, 07:51 OUT
```

- Order = time order (UI / PDF / edit modal same).
- Pairs: `parseLogDetailsToPairs()` — sequential IN→OUT.

---

## 10. MisPunch UI (`/mispunch`)

| Rule | Detail |
|------|--------|
| List filter | `status = MIS`, `attendance_date < today` |
| Hide deferred | `recordIsDeferredOpenInMispunch()` = true → list se hide |
| Sort | `employee_code` ascending |
| Name | `resolveEmployeeForBiometricRecord()` (branch scope safe) |

---

## 11. PDF reports

### 11.1 24H batch — `/reports/mispunch-download-24h`

| Rule | Detail |
|------|--------|
| Date | **Yesterday** only |
| Status | `MIS` |
| Include | Sirf `has_incomplete = true` (pending open IN **exclude**) |
| Sort | **Employee code** (`strnatcmp`) |

### 11.2 Selected forms — `mispunch-form-pdf`

- User selected IDs; sorted by employee code.

### 11.3 PDF / open IN enrich

- ESSL lookup bounded by `getShiftSessionEnd()`.
- OUT suggest only if `resolveShiftAttendanceDate()` = `attendance_date`.
- No `(+1 day)` label on PDF.

### 11.4 PDF pair issues

| Issue | Meaning |
|-------|---------|
| Pair N: missing OUT | IN hai, OUT nahi |
| Pair N: missing IN | OUT hai, IN nahi |

---

## 12. Real examples (production & rules)

### Example 1 — Normal day shift (valid)

```text
09:00 IN
18:00 OUT
```

| Item | Value |
|------|-------|
| Pair | Complete |
| Status | P |
| MIS | No |

---

### Example 2 — Normal day shift (double OUT) — employee 381

```text
09:02 IN
09:18 OUT
18:02 OUT
```

| Item | Value |
|------|-------|
| Pair 1 | 09:02 → 09:18 |
| Pair 2 | Missing IN |
| Status | MIS |

Normal shifts do **NOT** use extended night-session defer.

---

### Example 3 — Normal day shift (open IN)

**Same day:** Pending, NOT MIS yet (running day).  
**Next day:** MIS — day defer only for today.

---

### Example 4 — Night shift (valid cross midnight)

```text
2 Jun 21:00 IN
3 Jun 07:00 OUT
```

| Item | Value |
|------|-------|
| Shift day | 2 Jun |
| Status | P |

---

### Example 5 — Night shift (multiple valid pairs)

```text
2 Jun 21:00 IN → 3 Jun 01:00 OUT
3 Jun 03:00 IN → 3 Jun 06:00 OUT
```

| Item | Value |
|------|-------|
| Status | P |
| MIS | No |

---

### Example 6 — Night shift (open IN deferred)

```text
2 Jun 21:00 IN only
Current: 3 Jun 15:00 | Session end: 3 Jun 20:00
```

**Pending — NOT MIS**

---

### Example 7 — Night shift (defer expired)

```text
2 Jun 21:00 IN only
Current: 3 Jun 22:00 (after session end)
```

**MIS**

---

### Example 8 — Night shift (next punch = IN)

```text
2 Jun 21:00 IN
3 Jun 09:00 IN
```

**MIS** — Pair 1 missing OUT (Rule D2 + P2).

---

### Example 9 — Multi shift (morning OUT closes night)

```text
2 Jun 20:30 IN
3 Jun 07:30 OUT
```

Shift day: **2 Jun** | Status: **P**

---

### Example 10 — Multi shift (day grace IN) — e.g. employee 20942

```text
4 Jun 07:13 IN
```

Shift day: **4 Jun** (calendar day day-login), NOT previous day.

---

### Example 11 — Multi shift (orphan OUT)

```text
19:57 OUT (no prior IN)
```

**MIS** — missing IN.

---

### Example 12 — Production employee 20204

```text
07:55 IN, 09:11 IN, 09:24 OUT, 09:57 OUT, 17:43 OUT, 19:58 OUT
```

Multiple missing IN/OUT → **MIS**.

---

### Example 13 — Production employee 381

Same as Example 2 — orphan second OUT.

---

### Legacy examples (engine verification)

**A — Night multi-pair OK:** `21:00 IN → 01:00 OUT → 03:00 IN → 06:00 OUT` on one shift day.

**B — Next day IN = MIS (150):** `3 Jun IN → 4 Jun IN` without OUT.

**C — Double OUT:** `08:07 IN, 20:08 OUT, 20:10 OUT` → Pair 2 missing IN.

---

## 13. Re-sync zaroori

Code change ke baad purani `biometric_attendance` rows update **nahi** hoti jab tak sync na chale.

**Sync ab ye bhi karta hai:**

1. **`reconcileGapDaysInSyncRange`** — range mein jis din par koi punch group nahi (e.g. 3 Jun ESSL khali), purani galat MIS → **A / H / W**
2. **`clearMisplacedManualAttendanceInRange`** — manual row jisme punch us date par ESSL mein nahi (e.g. 07:55 sirf 4 Jun par) → manual flag hata kar reconcile

**Action:** ESSL Sync **1–4 Jun** → verify 21579: **4 Jun MIS**, **3 Jun A** (ya khali), duplicate 3 Jun MIS nahi.

---

## 14. Helper functions quick reference

| Function | Purpose |
|----------|---------|
| `resolveShiftAttendanceDate()` | Punch → shift day |
| `getShiftSessionStart()` / `getShiftSessionEnd()` | Session window |
| `groupEmployeePunchesByAttendanceDate()` | Pair + bucket |
| `analyzePunchSequence()` | MIS + log_details |
| `shouldDeferOpenInMispunch()` | Open IN defer (scoped) |
| `fetchFirstPunchAfterOpenIn()` | Next ESSL punch (session bounded) |
| `enrichMispunchPairsForRecord()` | PDF open-IN enrich |
| `buildMispunchReportRowFromRecord()` | PDF row |
| `resolveEmployeeForBiometricRecord()` | Employee + shift.slots |

---

## 15. Report status (what this doc covers)

After these rules, the report explains:

- Normal / night / multi-shift behavior  
- Open IN defer (scoped + day-only)  
- Chronological pairing + shift session day  
- Orphan OUT handling  
- Multiple valid pairs  
- PDF / 24H report logic  
- Production edge cases (20204, 381, 20942, 150)

Matches current attendance engine in `helper.php` + sync controller.

---

## 16. Related docs

- `docs/SHIFT_SESSION_ATTENDANCE_FIX.md`  
- `docs/ATTENDANCE_SYNC_ENGINE_REPORT.md`  

---

*Kiran Industries HR Attendance — MisPunch Rules Report (final validation).*
