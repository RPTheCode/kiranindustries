# Sidebar Redesign Plan — Kiran HR App

**Document:** Sidebar UX & UI improvement plan  
**Stack:** React + Inertia + Tailwind + shadcn `Sidebar`  
**Main files today:** `app-sidebar.tsx`, `nav-main.tsx`, `ui/sidebar.tsx`, `SidebarContext.tsx`

---

## 1. Current state (as-is)

### What exists today
- Logo + search box at top
- Flat / nested menu list (Masters, Employees, Attendance, Payroll, Reports, etc.)
- Permission-based menu items
- Menu search filter (already implemented)
- Collapsible submenus with localStorage state
- Settings for sidebar style (plain / colored / gradient) in brand settings

### Pain points (from UI review)
| Issue | Why it hurts users |
|--------|-------------------|
| Long flat list | Hard to scan; similar items feel equal priority |
| No section labels | “Attendance”, “ESSL Sync Log”, “Reports” look unrelated |
| Too much vertical gap | Wasted space; more scrolling on laptop |
| Weak active state | Only Dashboard looks selected; sub-routes unclear |
| Mixed naming | “Attendance & Bio-Sync” vs “ESSL Sync Log” — unclear relationship |
| Masters has 12+ children | Overwhelming dropdown |
| No collapse-to-icons mode hint | Users don’t know sidebar can shrink |
| Search is plain | No keyboard shortcut, no recent items |

---

## 2. Goals (user-friendly sidebar)

1. **Find any page in ≤ 2 clicks** (or 1 search)
2. **Group by job role** — daily work vs setup vs admin
3. **Clear “you are here”** — active parent + child highlighted
4. **Less scroll** — compact density option
5. **Mobile friendly** — drawer sidebar on small screens
6. **Role-aware** — Company / Admin / Manager / Staff see relevant menus only (already partial; improve labels)

---

## 3. Proposed menu structure (Information Architecture)

### Recommended groups (with section headers)

```
OVERVIEW
  └ Dashboard

PEOPLE
  └ Employees

TIME & ATTENDANCE
  └ Attendance
  └ MisPunch
  └ Production Entry
  └ ESSL Sync Log

PAYROLL
  └ Employee Salaries
  └ Earnings / Deductions
  └ Payroll Runs
  └ Payslips
  └ Advances
  └ Payroll Settings

REPORTS
  └ Daily / Attendance Reports
  └ Monthly Reports
  └ Master Reports
  └ Recent Downloads  ← quick link (new)

SETUP (Masters)
  └ Organization → Branches, Departments, Sections, Categories
  └ Workforce → Designations, Skills, Shifts, Week Offs
  └ Payroll Setup → Salary Components, Bank Masters, Overtime, Document Types
  └ Other → Material Items, Resign Reasons

ADMIN
  └ Activity Logs
  └ Media Library
  └ Settings
```

### Naming changes (simpler Hindi/English mix OK)
| Current | Suggested |
|---------|-----------|
| Attendance & Bio-Sync | Attendance |
| ESSL Sync Log | ESSL Sync (under Time & Attendance group visually) |
| Attendance Reports | Daily Reports |
| Masters | Setup |

---

## 4. Visual design plan

### 4.1 Layout
- **Width:** 260px expanded / 64px collapsed (icon rail)
- **Header:** Logo (small) + branch name chip (e.g. “Palsana”) + collapse toggle
- **Footer:** User avatar + name + role badge + logout

### 4.2 Section headers
- Small uppercase label: `text-[10px] font-semibold tracking-wider text-slate-400`
- Padding: `px-3 pt-4 pb-1`
- Example: `TIME & ATTENDANCE`

### 4.3 Menu item states
| State | Style |
|-------|--------|
| Default | `text-slate-600 hover:bg-slate-100` |
| Active parent | `bg-primary/10 text-primary font-medium border-l-2 border-primary` |
| Active child | Same + left indent |
| Icon | 18px, consistent Lucide set |

### 4.4 Compact mode (toggle in Settings)
- Reduce item height: `py-1.5` instead of `py-2.5`
- Hide section headers when collapsed to icons only
- Tooltips on hover when collapsed

### 4.5 Search upgrade
- Placeholder: `Search menu… (Ctrl+K)`
- Global shortcut `Ctrl+K` / `Cmd+K` opens command palette (optional Phase 2)
- Show “No results” + suggest Settings if permission missing

### 4.6 Colored sidebar (existing feature)
- Keep plain / colored / gradient
- Ensure white text contrast on colored mode
- Active item: `bg-white/20` instead of gray

---

## 5. UX behaviours to add

| Feature | Priority | Description |
|---------|----------|-------------|
| Auto-expand active section | P0 | Open parent when child route active (partially done) |
| Remember expanded groups | P0 | localStorage per user (partially done) |
| Collapse other groups | P1 | Accordion: only one top-level open at a time (optional) |
| Favourites / Pin | P2 | User pins 3–5 frequent links at top |
| Recent pages | P2 | Last 5 visited routes under search |
| Badge counts | P1 | MisPunch pending count, failed sync count |
| Branch switcher in sidebar | P1 | Move from header if duplicated |

---

## 6. Technical implementation plan

### Phase 1 — Quick wins (1–2 days)
**No route changes. UI + structure only.**

1. Add `NavSection` component with group labels
2. Refactor `getCompanyNavItems()` to return grouped structure:

```ts
type NavSection = {
  label: string;
  items: NavItem[];
};
```

3. Update `nav-main.tsx`:
   - Render section label before each group
   - Stronger active styles (border-left + bg)
   - Tighter spacing classes

4. Sidebar header:
   - Show active branch name under logo
   - Add collapse button with tooltip

5. Rename menu labels (translation keys in `en.json` / `hi.json`)

**Files to edit:**
- `resources/js/components/app-sidebar.tsx`
- `resources/js/components/nav-main.tsx`
- `resources/js/types/index.d.ts` (add `NavSection`)
- `resources/js/components/ui/sidebar.tsx` (minor spacing tokens)

### Phase 2 — Masters submenu cleanup (2–3 days)

1. Split Masters into 3 sub-groups inside dropdown OR 3 separate collapsible blocks:
   - Organization
   - Workforce
   - Payroll setup
2. Add icons per master item (Building2, Users, Coins…)
3. Alphabetical sort within each sub-group

### Phase 3 — Search & shortcuts (1–2 days)

1. `Ctrl+K` command palette (shadcn `Command` dialog)
2. Recent routes in `localStorage`
3. Optional: favourite pins

**New file:** `resources/js/components/nav-command-palette.tsx`

### Phase 4 — Mobile & collapsed mode (1–2 days)

1. Verify `Sheet` sidebar on `< md`
2. Icon-only rail with tooltips (`Tooltip` from shadcn)
3. Test RTL if used

### Phase 5 — Polish & settings (1 day)

1. Sidebar density toggle in Settings → Appearance
2. Preview in existing `sidebar-preview.tsx`
3. QA all roles: company, admin, manager, staff

---

## 7. Suggested wireframe (text)

```
┌─────────────────────────┐
│  [Logo]  Kiran          │
│  Branch: Palsana    [≡] │
├─────────────────────────┤
│ 🔍 Search menu (Ctrl+K) │
├─────────────────────────┤
│ OVERVIEW                │
│  ▣ Dashboard      ●     │  ← active
│                         │
│ TIME & ATTENDANCE       │
│  ⏱ Attendance        ▾  │
│     · Attendance        │
│     · MisPunch          │
│     · Production Entry  │
│  📄 ESSL Sync           │
│                         │
│ REPORTS                 │
│  📊 Reports          ▾  │
│                         │
│ SETUP                   │
│  🗄 Masters          ▾  │
├─────────────────────────┤
│ 👤 Admin User           │
│    Company · Logout     │
└─────────────────────────┘
```

---

## 8. Role-based visibility (keep + improve)

| Role | Sidebar focus |
|------|----------------|
| Company | Full menu |
| Admin | Hide company-only if needed; full HR |
| Manager | Attendance, Reports, Employees (branch scoped) |
| Staff | Minimal: own attendance / payslip if enabled |

**Action:** Audit `hasPermission()` in `getCompanyNavItems()` — document which role sees what in this MD file appendix when done.

---

## 9. Testing checklist

- [ ] Active route highlights correct parent + child
- [ ] Search finds nested items (e.g. “MisPunch”, “Department”)
- [ ] Collapsed sidebar shows tooltips
- [ ] Colored / gradient themes readable
- [ ] Mobile drawer opens/closes without scroll lock bug
- [ ] Permission removed → menu item hidden, no broken route
- [ ] RTL layout (if enabled)
- [ ] Long department/branch names truncate with ellipsis

---

## 10. Success metrics

| Metric | Target |
|--------|--------|
| Clicks to reach Attendance Reports | ≤ 2 |
| User complaints “can’t find menu” | ↓ 80% |
| Sidebar scroll height (1080p) | Fit 80% items without scroll |
| Support tickets on navigation | ↓ within 2 weeks of release |

---

## 11. Recommended rollout order

```
Week 1 → Phase 1 (sections + active state + labels)
Week 2 → Phase 2 (Masters grouping)
Week 3 → Phase 3 (Ctrl+K search) + Phase 4 (mobile)
Week 4 → Phase 5 (settings + QA + user feedback)
```

---

## 12. Do NOT change in sidebar redesign

- Route names / URLs (avoid breaking bookmarks)
- Permission logic (only improve grouping)
- Backend modules
- Report generation flows

---

## 13. Optional future (Phase 6+)

- Dark mode sidebar variant
- Per-user custom menu order (drag & drop)
- Hindi menu labels toggle
- “What’s new” dot on new features

---

## 14. Quick start — if you want to begin today

**Minimum viable improvement (4–6 hours):**

1. Add section headers: OVERVIEW, TIME & ATTENDANCE, PAYROLL, REPORTS, SETUP, ADMIN  
2. Rename “Attendance & Bio-Sync” → “Attendance”  
3. Active item: left border + light primary background  
4. Reduce vertical padding on menu items  
5. Show branch name under logo  

This alone will make the sidebar feel much more professional and user-friendly.

---

**Prepared for:** Kiran Industries HR System  
**Last updated:** June 2026  
**Owner:** Frontend / UX  
**Status:** Plan only — implementation not started unless approved
