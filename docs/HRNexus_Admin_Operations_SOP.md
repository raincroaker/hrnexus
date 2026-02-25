# HRNexus Admin Operations SOP

## 1. Purpose
This SOP guides Admin users and IT/HR operations teams in running the hosted HRNexus system for daily use, onboarding, and stakeholder turnover.

## 2. Scope
Applies to:
- System administrators
- HR operations
- Department managers (for delegated actions)

## 3. Access and Security Baseline
- Login with seeded admin account:
  - `admin@hrnexus.com` / `password`
- Immediately perform:
  1. Password change
  2. Creation of named admin accounts (avoid shared login long-term)
  3. Optional 2FA enablement (recommended)

## 4. Initial System Readiness Procedure
1. **Organization Setup**
   - Create departments and positions.
   - Validate naming conventions.
2. **User and Employee Setup**
   - Add employees with complete profile fields.
   - Assign role and department correctly.
   - Confirm employment lifecycle fields (active/inactive, reason, dates) where applicable.
3. **Attendance Configuration**
   - Set attendance settings (required time in/out, break behavior).
   - Confirm warning/memorandum thresholds align with policy.
4. **Operational Modules Validation**
   - Documents: upload, approve/reject, preview, search.
   - Chats: create group, member management, messaging.
   - Calendar: categories and events.

## 5. Daily Admin Runbook

### 5.1 Attendance
- Review late, absent, and leave indicators.
- Validate overtime and leave records entered that day.
- Generate DTR exports as requested.

### 5.2 Employees
- Process onboarding/offboarding updates.
- Update employment status and inactivity reasons when needed.

### 5.3 Documents
- Process pending approvals.
- Respond to access requests.
- Spot-check search and preview behavior.

### 5.4 Chats and Calendar
- Ensure communication channels are functioning.
- Verify important events are published and categorized.

## 6. Weekly Admin Checks
- Review role assignments and access scope.
- Validate unusual attendance patterns and escalations.
- Review unresolved warning/memorandum items.
- Verify critical reports can be generated successfully.

## 7. Change Management
- For configuration changes (attendance rules, role structure, policy thresholds):
  1. Document reason and requester.
  2. Apply during low-impact window.
  3. Perform smoke check after change.
  4. Notify affected users.

## 8. Incident Handling (High-Level)
- **Login/access failure:** verify user role, status, and credentials.
- **Module data mismatch:** reload, re-check record source, verify successful save response.
- **File/search issues:** verify document approval/access and record existence.
- **Permission conflict:** validate user role and expected policy behavior.

Escalate to technical support if issue persists after verification.

## 9. Turnover Package Contents
For stakeholder handover, include:
- This SOP
- Quick Start One-Pager
- Hosted URL and support contacts
- Default account policy note (must change password on first use)
- Current known limitations (if any)

## 10. Sign-Off Checklist
- [ ] Default admin credential secured
- [ ] Named admin(s) created
- [ ] Departments/positions configured
- [ ] Pilot users tested (admin, manager, employee)
- [ ] Core module checks completed
- [ ] Stakeholders oriented with manual and SOP

