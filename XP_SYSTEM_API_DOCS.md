# XP & Leveling System — Frontend API Documentation

**Base URL:** `/api/v1`
**Last Updated:** 2026-03-24

---

## Overview

The XP system rewards users for ordering, reviewing, streaks, referrals, and completing challenges. Users earn XP, level up, unlock prizes, and compete on leaderboards.

### XP Sources

| Source | Default XP | When Awarded |
|---|---|---|
| Order completion | 20 (flat) | Order delivered |
| Item purchase | `floor(price × qty × multiplier × 0.1)` per item | Order delivered |
| Review | 30 | Review submitted |
| Signup bonus | 50 | Account created |
| Streak bonus | 10 | Order delivered (if streak > 1 day) |
| Referral bonus | 50 | Referred user's first order delivered |
| Daily challenge | 20 | User claims completed challenge |
| Weekly challenge | 100 | User claims completed challenge |

### Module Multipliers

| Module | Multiplier |
|---|---|
| food | 1.0 |
| ecommerce | 1.0 |
| service | 1.0 |
| pharmacy | 0.5 |
| grocery | 0.25 |
| parcel | 0.1 |

### Levels (Default Seed)

| Level | Name | XP Required |
|---|---|---|
| 1 | Starter | 50 |
| 2 | Lowkey | 200 |
| 3 | Vibing | 600 |
| 4 | Locked-In | 1,200 |
| 5 | Main Character | 2,000 |
| 6 | Certified | 3,000 |
| 7 | Elite Mode | 4,200 |
| 8 | Goated | 5,600 |
| 9 | Iconic | 7,200 |
| 10 | Legendary | 9,000 |

---

## Endpoints

### 1. `GET /xp/config` — Public (No Auth)

XP configuration for client-side display/calculation. Cache on app startup.

**Response:**
```json
{
  "enabled": true,
  "xp_per_order": 20,
  "xp_per_review": 30,
  "xp_signup_bonus": 50,
  "max_level": 10,
  "multipliers": {
    "food": 1.0,
    "grocery": 0.25,
    "pharmacy": 0.5,
    "ecommerce": 1.0,
    "parcel": 0.1,
    "service": 1.0
  },
  "multiplier_event": {
    "active": false,
    "multiplier": 1.0,
    "ends_at": null
  },
  "streak_bonus_xp": 10
}
```

**Notes:**
- When `enabled` is `false`, hide the entire XP UI.
- When `multiplier_event.active` is `true` and `ends_at` is in the future, all item XP is multiplied by `multiplier_event.multiplier`. Show a banner/badge to the user.

---

### 2. `GET /xp/level` — Auth Required

Current user's level and XP progress.

**Response:**
```json
{
  "current_level": 3,
  "level_name": "Vibing",
  "level_badge": "https://example.com/storage/level/vibing.png",
  "total_xp": 750,
  "xp_to_next_level": 450,
  "progress_percentage": 33,
  "is_max_level": false,
  "next_level": {
    "level_number": 4,
    "name": "Locked-In",
    "xp_required": 1200
  }
}
```

**Notes:**
- `progress_percentage` is 0–100, use for progress bar.
- When `is_max_level` is `true`, `next_level` is `null`.

---

### 3. `GET /xp/levels` — Auth Required

All levels with prizes. Use for the "Levels" screen.

**Response:**
```json
{
  "levels": [
    {
      "level_number": 1,
      "is_unlocked": true,
      "name": "Starter",
      "xp_required": 50,
      "description": "Welcome to Waddy!",
      "badge_image": "https://...",
      "prizes": [
        {
          "id": 1,
          "instance_id": 42,
          "title": "Starter Badge",
          "description": "Welcome badge",
          "prize_type": "badge",
          "value": null,
          "validity_days": null,
          "status": "used",
          "is_claimed": true,
          "is_unlocked": true
        }
      ]
    }
  ],
  "current_level": 3,
  "current_xp": 750,
  "xp_for_next_level": 1200,
  "xp_to_next_level": 450,
  "progress_percentage": 33
}
```

**Prize `status` values:**
- `null` — level not yet reached, prize locked
- `"unlocked"` — available to claim
- `"claimed"` — claimed but not yet used (e.g. free delivery)
- `"used"` — consumed
- `"expired"` — validity period ended

**Prize `prize_type` values:**
- `badge` — auto-completed on unlock, display only
- `free_delivery` — usable at checkout
- `wallet_credit` — auto-credited to wallet on claim
- `free_item` — free item reward
- `discount` — order discount
- `custom` — special reward (e.g. birthday treat)

**Notes:**
- `instance_id` is the user's prize instance ID. Use this for claiming (`POST /xp/prizes/{instance_id}/claim`).
- `is_unlocked: false` means the level hasn't been reached yet — show as locked/greyed out.

---

### 4. `GET /xp/level-details` — Auth Required

**Combined endpoint** — level info + all levels + streak data. Use this instead of calling `/level` + `/levels` separately.

**Response:** Same as `/xp/levels` plus these extra fields:
```json
{
  "...same as /levels...",
  "level_name": "Vibing",
  "level_badge": "https://...",
  "is_max_level": false,
  "next_level": { "level_number": 4, "name": "Locked-In", "xp_required": 1200 },
  "streak": {
    "current_streak": 3,
    "longest_streak": 7,
    "streak_bonus_xp": 10,
    "last_activity_date": "2026-03-23"
  }
}
```

**Notes:**
- `streak.current_streak` = consecutive days with a delivered order.
- If `current_streak > 1`, user earns `streak_bonus_xp` on next order.
- `last_activity_date` is `null` if user has never ordered.

---

### 5. `GET /xp/history` — Auth Required

Unified XP activity feed with level-up events injected.

**Query params:**
| Param | Required | Type | Notes |
|---|---|---|---|
| `limit` | Yes | int (1–50) | Items per page |
| `offset` | Yes | int (≥1) | Page number |

**Response:**
```json
{
  "history": [
    {
      "type": "level_up",
      "xp": 0,
      "description": "Reached Level 3: Vibing",
      "created_at": "2026-03-20T14:30:00+00:00"
    },
    {
      "type": "order",
      "xp": 25,
      "description": "Shawarma — 250 LE × 1 (x1 multiplier)",
      "created_at": "2026-03-20T14:30:00+00:00"
    },
    {
      "type": "order",
      "xp": 20,
      "description": "Order completed",
      "created_at": "2026-03-20T14:30:00+00:00"
    },
    {
      "type": "streak",
      "xp": 10,
      "description": "Streak day 3 bonus",
      "created_at": "2026-03-20T14:30:00+00:00"
    },
    {
      "type": "challenge",
      "xp": 100,
      "description": "Completed: Order 3 Times This Week",
      "created_at": "2026-03-19T10:00:00+00:00"
    }
  ],
  "total_earned": 1250,
  "total_size": 47,
  "limit": 10,
  "offset": 1
}
```

**`type` values:** `order`, `review`, `challenge`, `signup`, `streak`, `referral`, `level_up`, `other`

---

### 6. `GET /xp/transactions` — Auth Required

Raw XP transaction log (for "detailed history" or debug view).

**Query params:** Same as `/history` (`limit`, `offset`).

**Response:**
```json
{
  "total_size": 47,
  "limit": 10,
  "offset": 1,
  "transactions": [
    {
      "id": 123,
      "user_id": 5,
      "reference_type": "order_detail",
      "reference_id": 456,
      "xp_source": "item_purchase",
      "xp_amount": 25,
      "balance_after": 750,
      "description": "Shawarma — 250 LE × 1 (x1 multiplier)",
      "is_reversed": false,
      "created_at": "2026-03-20T14:30:00.000000Z",
      "updated_at": "2026-03-20T14:30:00.000000Z"
    }
  ]
}
```

**`xp_source` values:** `completion_bonus`, `item_purchase`, `review_bonus`, `signup_bonus`, `streak_bonus`, `referral_bonus`, `daily_challenge`, `weekly_challenge`, `admin_manual`

---

### 7. `GET /xp/challenges` — Auth Required

User's current daily and weekly challenges.

**Response:**
```json
{
  "challenges": {
    "daily": {
      "id": 12,
      "challenge_id": 3,
      "title": "Treat Yourself",
      "description": "Place an order of at least 150 EGP",
      "type": "daily",
      "challenge_type": "min_order_amount",
      "xp_reward": 20,
      "status": "active",
      "progress": {
        "amount_spent": 0,
        "target": 150
      },
      "conditions": { "min_amount": 150 },
      "started_at": "2026-03-24T00:00:00+00:00",
      "expires_at": "2026-03-25T00:00:00+00:00",
      "completed_at": null
    },
    "weekly": {
      "id": 15,
      "challenge_id": 5,
      "title": "Order 3 Times This Week",
      "description": "Complete 3 orders this week",
      "type": "weekly",
      "challenge_type": "multiple_orders",
      "xp_reward": 100,
      "status": "active",
      "progress": {
        "orders_completed": 1,
        "target": 3
      },
      "conditions": { "order_count": 3 },
      "started_at": "2026-03-22T00:00:00+00:00",
      "expires_at": "2026-03-29T00:00:00+00:00",
      "completed_at": null
    }
  },
  "has_daily": true,
  "has_weekly": true
}
```

**`status` values:**
- `"active"` — in progress, show progress bar
- `"completed"` — done, show "Claim" button
- `"claimed"` — already claimed, show checkmark (shouldn't appear here normally)

**`challenge_type` values and progress shapes:**

| challenge_type | progress | How to display |
|---|---|---|
| `complete_order` | `{ "completed": false }` | Binary — done or not |
| `min_order_amount` | `{ "amount_spent": 0, "target": 250 }` | Show `amount_spent / target` |
| `multiple_orders` | `{ "orders_completed": 1, "target": 3 }` | Show `orders_completed / target` |
| `new_store` | `{ "completed": false }` | Binary — done or not |

**Notes:**
- Challenges are auto-assigned when this endpoint is called (lazy assignment).
- Daily resets at midnight. Weekly resets on Saturday.
- If `has_daily` or `has_weekly` is `false`, show "No challenge available" or hide the section.

---

### 8. `POST /xp/challenges/{id}/claim` — Auth Required

Claim reward for a completed challenge.

**URL param:** `id` = the `id` from the challenge object (NOT `challenge_id`).

**Request body:** None.

**Success (200):**
```json
{
  "message": "Challenge reward claimed",
  "xp_earned": 100,
  "new_total_xp": 850,
  "new_level": 3
}
```

**Errors:**
- `404` — Challenge not found or doesn't belong to user
- `403` — Challenge not in `completed` status

**Notes:**
- After claiming, re-fetch `/xp/challenges` to get the next available challenge.
- After claiming, update the XP bar and level display with `new_total_xp` / `new_level`.

---

### 9. `GET /xp/prizes` — Auth Required

All user's prizes grouped by status.

**Response:**
```json
{
  "usable_prizes": [
    {
      "id": 42,
      "prize_id": 5,
      "level": 3,
      "level_name": "Vibing",
      "title": "Free Delivery",
      "description": "One free delivery on your next order",
      "prize_type": "free_delivery",
      "value": null,
      "min_order_amount": 100,
      "usage_limit": 1,
      "status": "claimed",
      "is_usable": true,
      "unlocked_at": "2026-03-15T10:00:00+00:00",
      "expires_at": "2026-04-14T10:00:00+00:00",
      "used_at": null
    }
  ],
  "used_prizes": [],
  "expired_prizes": []
}
```

**Notes:**
- `usable_prizes` includes both `unlocked` and `claimed` prizes that haven't expired and pass period limits.
- `is_usable: true` means it can be applied. Always check this rather than status alone.

---

### 10. `POST /xp/prizes/{id}/claim` — Auth Required

Claim an unlocked prize.

**URL param:** `id` = the prize `id` from `/xp/prizes` response (the `id` field, not `prize_id`).

**Request body:** None.

**Success (200):**
```json
{
  "message": "Prize claimed successfully",
  "prize": {
    "id": 42,
    "title": "Free Delivery",
    "type": "free_delivery",
    "value": null,
    "status": "claimed"
  }
}
```

**Errors:**
- `404` — Prize not found
- `403` — Already claimed/used, or expired

**Prize type behavior on claim:**
| Type | What happens |
|---|---|
| `wallet_credit` | Credits added to wallet immediately. Status becomes `used`. |
| `free_delivery` | Status becomes `claimed`. Usable at checkout. |
| `free_item` | Status becomes `claimed`. |
| `discount` | Status becomes `claimed`. |
| `badge` | Auto-claimed on unlock. No manual claim needed. |

---

### 11. `GET /xp/checkout-prizes` — Auth Required

Free delivery prizes available at checkout. Show these as selectable options.

**Query params:**
| Param | Required | Type | Notes |
|---|---|---|---|
| `order_amount` | No | float | Filter by min_order_amount eligibility |

**Response:**
```json
{
  "prizes": [
    {
      "id": 42,
      "title": "Free Delivery",
      "min_order_amount": 100,
      "expires_at": "2026-04-14T10:00:00+00:00",
      "level_name": "Vibing"
    }
  ]
}
```

**Usage at checkout:**
- Pass the selected prize `id` as `use_prize_id` in the place order request.
- If the prize is valid, delivery charge becomes 0.
- If invalid (expired, already used, etc.), the prize is silently ignored and normal delivery charge applies.

---

### 12. `GET /xp/reward-items` — Auth Required

Reward items available at a specific store (for free_item prizes).

**Query params:**
| Param | Required | Type | Notes |
|---|---|---|---|
| `store_id` | Yes | int | Store ID |
| `reward_type` | No | string | `free_item`, `free_side`, or `birthday_gift` |

**Response:**
```json
{
  "reward_items": [
    {
      "id": 1,
      "item_id": 234,
      "item_name": "Coca Cola 330ml",
      "item_image": "https://...",
      "reward_type": "free_item",
      "max_value": 30
    }
  ]
}
```

---

### 13. `GET /xp/leaderboard` — Auth Required

Top 20 users by XP.

**Query params:**
| Param | Required | Type | Notes |
|---|---|---|---|
| `type` | No | string | `global` (default) or `zone` |

**Response:**
```json
{
  "leaderboard": [
    {
      "rank": 1,
      "name": "Ahmed M.",
      "total_xp": 5200,
      "level": 8,
      "image": "https://..."
    },
    {
      "rank": 2,
      "name": "Sara K.",
      "total_xp": 4800,
      "level": 7,
      "image": null
    }
  ],
  "my_rank": 15,
  "my_xp": 750,
  "my_level": 3,
  "type": "global"
}
```

---

## Item Response — `potential_xp` Field

Every item returned from product endpoints now includes a `potential_xp` field:

```json
{
  "id": 45,
  "name": "Shawarma Plate",
  "price": 250,
  "potential_xp": 25,
  "...other fields..."
}
```

**Formula:** `floor(price × 1 × module_multiplier × 0.1)`

This is the XP for 1 unit. For cart display, multiply by quantity:
```
displayed_xp = potential_xp × quantity
```

When an event multiplier is active, the actual XP earned at delivery may be higher than `potential_xp` (which is calculated without the event multiplier for caching reasons). You can apply it client-side:
```
event_xp = potential_xp × multiplier_event.multiplier
```

---

## Order Placement — Using a Prize

When placing an order, pass the prize to use:

```
POST /api/v1/customer/order/place

{
  "...other order fields...",
  "use_prize_id": 42
}
```

- `use_prize_id` = the `id` from `/xp/checkout-prizes` or `/xp/prizes`
- Only `free_delivery` prizes are supported at checkout currently
- Prize must be `unlocked` or `claimed` status, not expired, and pass min_order_amount check
- If valid, delivery charge is set to 0

---

## Typical UI Flows

### Home Screen
1. Call `GET /xp/level-details` on load
2. Show level badge, name, XP bar (`progress_percentage`), streak flame icon with `current_streak`
3. Show daily/weekly challenge cards from the `challenges` response within level-details, or make a separate call to `GET /xp/challenges`

### Levels Screen
1. Use data from `GET /xp/level-details`
2. Show all levels vertically, current level highlighted
3. Locked levels greyed out
4. Each level shows its prizes with status badges (locked/unlocked/claimed/used)
5. "Claim" button on prizes with `status: "unlocked"`

### Challenge Card
1. `GET /xp/challenges`
2. Show progress bar: `progress.orders_completed / progress.target` (for multiple_orders)
3. When `status: "completed"`, show "Claim XP" button → `POST /xp/challenges/{id}/claim`
4. After claiming, re-fetch challenges for the next one

### Checkout
1. `GET /xp/checkout-prizes?order_amount={subtotal}`
2. If prizes available, show "Use Free Delivery" toggle/selector
3. Pass `use_prize_id` in place order request
4. Show estimated XP earn: sum of `potential_xp × qty` for all cart items + `xp_per_order` (20)

### History Screen
1. `GET /xp/history?limit=20&offset=1`
2. Show chronological feed with icons per type
3. `level_up` entries should be highlighted (banner style)
4. Paginate with offset

### Leaderboard
1. `GET /xp/leaderboard?type=global`
2. Show top 20 + "Your rank" card at bottom
3. Tab between `global` and `zone`
