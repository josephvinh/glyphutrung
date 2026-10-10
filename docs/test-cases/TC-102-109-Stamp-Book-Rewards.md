# TC-102 to TC-109: Stamp Book & Rewards

## Test Scope

Testing stamp book balance, reward redemption, and pre-order confirmation.

## Test Cases

| ID | Description |
|----|-------------|
| TC-102 | View student stamp balance |
| TC-103 | Scan card and view balance |
| TC-104 | Add item to cart |
| TC-105 | Confirm redemption |
| TC-106 | Insufficient stamps warning |
| TC-107 | Out of stock display |
| TC-108 | Confirm pre-order with password |
| TC-109 | Override with Thủ Thư permission |

## Redemption Flow (POS)

1. Scan card or enter code
2. View available stamps
3. Select gifts
4. Confirm redemption
5. Stamps deducted, inventory reduced

## Pre-order Flow (Online)

1. Student places order on somoc.php
2. Order status: chờ lấy
3. Thủ Thư confirms at counter
4. Enter student password OR override
5. Order status: đã giao

## Expected Results

### Stamp Balance

- current_balance: Available for spending
- held_balance: Reserved for pending orders
- streak: Consecutive attendance days

### Redemption Validation

- Cannot exceed available balance
- Cannot exceed stock
- Minimum stamp cost: 1

### Pre-order Confirmation

- Enter password: Validates against student's stamp_password
- Override: Thủ Thư permission bypasses password
- Cancel: Refunds held stamps

### Beep Feedback

- Success: Two ascending beeps (880Hz, 1320Hz)
- Error: Low tone beep
- Vibration on mobile

## Backend APIs

- POST /api/rewards.php?action=lookup
- POST /api/rewards.php?action=redeem
- POST /api/rewards.php?action=confirm
- POST /api/rewards.php?action=cancel_staff
- POST /api/gifts.php?action=list

## Permission

- rewards module: thu_thu and admin only
- student can view own stamps via somoc.php
