# TC-89 to TC-101: Announcements Module

## Test Scope

Testing announcement creation, audience targeting, RSVP, and management.

## Test Cases

| ID | Description |
|----|-------------|
| TC-89 | Display published announcements |
| TC-90 | Badge showing unread count |
| TC-91 | Mark as read when opened |
| TC-92 | Mark all as read |
| TC-93 | Create announcement for all (toàn đoàn) |
| TC-94 | Create announcement for block |
| TC-95 | Create announcement for class |
| TC-96 | Announcement with meeting schedule (RSVP) |
| TC-97 | Urgent priority announcement |
| TC-98 | Edit published announcement |
| TC-99 | Withdraw announcement (draft status) |
| TC-100 | Delete announcement |
| TC-101 | Trưởng Khối can only edit own block announcements |

## Announcement Fields

| Field | Type | Description |
|-------|------|-------------|
| title | string | Announcement title |
| body | string | Content |
| level | enum | thường, quan trọng, khẩn |
| audienceType | enum | toàn đoàn, khối, lớp |
| audienceValue | string | Block or class name |
| status | enum | đã phát, nháp |
| isMeeting | boolean | Has meeting schedule |
| meetingAt | datetime | Meeting date/time |
| meetingPlace | string | Meeting location |
| expiresAt | date | Expiration date (optional) |

## Expected Results

### Audience Targeting

- Toàn đoàn: Visible to all members
- Khối: Visible to members in that block
- Lớp: Visible to members and students in that class

### Trưởng Khối Permission

- Can create/edit announcements for their block only
- Cannot modify BĐH's toàn đoàn announcements

### Meeting RSVP

- Participants can mark: tham gia, không tham gia
- Creator can view RSVP results

### Priority Colors

- Thường: Blue
- Quan trọng: Amber
- Khẩn: Red

## Backend APIs

- POST /api/announcements.php?action=save
- POST /api/announcements.php?action=toggle
- POST /api/announcements.php?action=delete
- POST /api/announcements.php?action=read
- POST /api/announcements.php?action=readall
- POST /api/announcements.php?action=rsvp
- GET /api/announcements.php?action=rsvpList
