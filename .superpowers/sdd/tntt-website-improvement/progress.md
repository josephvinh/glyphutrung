# SDD ledger — plan: G:\xampp\htdocs\tntt\docs\superpowers\plans\2026-08-31-tntt-website-improvement.md

## Project: TNTT Super App - Website Improvement Plan

### Task Checklist
- [x] Task 1: Security - CSRF Protection
- [x] Task 2: Security - Password Hashing Upgrade (bcrypt → argon2id)
- [x] Task 3: Security - Security Headers
- [x] Task 4: Performance - Database Index Optimization
- [ ] Task 5: Performance - Caching Layer
- [ ] Task 6: UX - Dark Mode
- [ ] Task 7: UX - Skeleton Loading States
- [ ] Task 8: Feature - Enhanced Dashboard
- [ ] Task 9: Feature - Calendar View
- [ ] Task 10: Code Quality - CI/CD Pipeline

## Phase 1: Security (Tasks 1-4)
### Task 1: CSRF Protection ✅ DONE
### Task 2: Password Hashing ✅ DONE
### Task 3: Security Headers ✅ DONE
### Task 4: Database Indexes ✅ DONE

## Phase 2: Performance (Tasks 5)
### Task 5: Caching Layer

## Phase 3: UX (Tasks 6-7)
### Task 6: Dark Mode
### Task 7: Skeleton Loading

## Phase 4: Features (Tasks 8-9)
### Task 8: Enhanced Dashboard
### Task 9: Calendar View

## Phase 5: Code Quality (Task 10)
### Task 10: CI/CD Pipeline

## Preflight Scan Results
Check: tasks that contradict each other or Global Constraints
- No contradictions found between tasks
- All tasks follow the same architecture (PHP + Alpine.js + MySQL)

Check: plan explicitly mandates vs review rubric treats as defect
- Task 1-4 all have clear specifications
- Each task has test specifications

## Preflight Task Pair Analysis
| Task A | Task B | Interface | Conflict? |
|--------|--------|-----------|-----------|
| Task 1 (CSRF) | Task 2 (Password) | Different files | None |
| Task 1 (CSRF) | Task 3 (Headers) | Different files | None |
| Task 2 (Password) | Task 3 (Headers) | Different files | None |
| Task 4 (Indexes) | Task 5 (Cache) | schema.sql | None - indexes complement caching |

## Global Constraints (from plan)
- Tech Stack: PHP 8.x, PDO/MySQLi, Alpine.js, Tailwind CSS, MySQL/MariaDB
- No external CDN dependencies (self-hosted fonts/icons)
- All new code must integrate with existing TNTT_BOOT structure
- Password hashing: Argon2id with memory_cost=65536, time_cost=4, threads=3
- CSRF: 32-byte random tokens, stored in session

## Rulings
- **module_attendance.php/module_settings.php**: No `<form>` tags found in these files. Reviewer flagged them for missing CSRF inputs but forms don't exist. Ruling: N/A - files have no forms requiring CSRF protection.

## Deferred Minor Issues
(No deferred issues yet)

## Current Status
Phase 1: Bảo Mật - In Progress

## Task 1 Progress
- Agent dispatched: a9faee09d7de69d06
- BASE commit: 4fbc0ae
- Brief: task-1-brief.md
- Report: task-1-report.md
- Status: ✅ DONE - 4/4 tests passing
- Reviewer: a55ed845cca75c5
- Fix round: Applied (require_csrf() added to all endpoints, core.js updated)

## Implementation Log
| Task | Status | Agent | BASE | HEAD | Review |
|------|--------|-------|------|------|--------|
| Task 1 | ✅ Complete | a9faee09d7de69d06 | 4fbc0ae | 61495a3 | Approved |
| Task 2 | ✅ Complete | a5ad719472a708738 | 61495a3 | f93a5ec | Approved |
| Task 3 | ✅ Complete | a3244a607069d4f3c | f93a5ec | 598d653 | Approved |
| Task 4 | ✅ Complete | a9cbc4ef0b18d9a5d | 598d653 | - | Approved |
