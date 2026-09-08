# Jify Discount

**[下載 v2.1.0 安裝 ZIP](https://github.com/yves01480/jify-discount/releases/download/v2.1.0/jify-discount.zip)** · [版本紀錄](https://github.com/yves01480/jify-discount/releases) · [試用／問題回報](https://github.com/yves01480/jify-discount/issues/new?template=store-feedback.md)

免費開源（GPL-2.0-or-later）。已有 WordPress＋WooCommerce 商店即可在測試站開始；主機與其他服務費用另計。

## 滿額優惠自動套用，顧客不用記折扣碼。

適合要對指定商品或規格安排滿額促銷的 WooCommerce 店家。可以設定固定金額、百分比與活動日期，達到門檻後自動計算優惠。

### 滿額門檻與折扣對象，分開看就清楚

假設購物車商品金額 NT$2,400，其中符合優惠的商品明細為 NT$1,200：

| 商品設定的規則 | 這筆商品明細的折扣 |
|---|---:|
| 商品金額滿 NT$1,000，折 NT$100 | NT$100 |
| 商品金額滿 NT$2,000，折 15% | NT$180 |

兩個門檻都符合時，這筆明細採用較大的 **NT$180** 折扣。

- **指定商品／規格：** 將活動設在要促銷的商品上。
- **安排活動期間：** 依 WordPress 網站時區設定開始與結束日期。
- **顯示促銷訊息：** 在折扣金額旁補上活動說明。

**第一次試用：** 到 **商品資料 → Jify Discount** 啟用一個商品的規則，測試門檻以下、剛好達標與多筆符合商品的購物車。

> 固定折扣是「每筆符合資格的商品明細」計算；門檻不包含運費，介面的 Include Shipping 選項目前不生效。折扣以負費用列呈現，可能與優惠券疊加；上線前請核對你的活動總折扣。

### 下載後怎麼安裝

1. 下載上方 **jify-discount.zip**，不需解壓縮。
2. 到 WordPress **外掛 → 安裝外掛 → 上傳外掛**，選取 ZIP 後安裝、啟用。
3. 依上面的第一次試用情境設定；版本需求與完整行為請見下方英文文件。

有想套用的店家情境？[告訴我你的設定與預期結果](https://github.com/yves01480/jify-discount/issues/new?template=store-feedback.md)，也歡迎回報第一次安裝卡在哪一步。GitHub Issue 是公開的，請使用測試資料。

---

## English documentation

> Product- and variation-aware threshold discounts for WooCommerce, with fixed or percentage rules, scheduling, and item-level discount data for downstream tax calculations.

![License: GPLv2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)
![WordPress 5.8+](https://img.shields.io/badge/WordPress-5.8%2B-21759b)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![Stable](https://img.shields.io/badge/stable-2.1.0-brightgreen)

Jify Discount is a self-hosted WooCommerce plugin for stores that need automatic promotions without asking customers to enter coupon codes.

Rules are configured on individual products or variations. When the cart reaches a configured threshold, the plugin calculates the best eligible fixed or percentage discount for each matching cart line, combines those item discounts, and applies the result as a negative WooCommerce fee.

## Why This Exists

WooCommerce coupons work well for customer-entered promotion codes, but some stores need promotions that:

- activate automatically when the cart reaches a spending threshold;
- apply only to selected products or variations;
- run during a defined campaign period;
- expose item-level discount amounts to another calculation layer;
- display a promotion message directly beside the discount total.

Jify Discount implements that workflow while keeping the promotion logic inside the store.

## Key Features

- **Cart-threshold rules** — activate rules when the merchandise subtotal reaches a configured amount.
- **Fixed and percentage discounts** — define a fixed value or a percentage for each threshold.
- **Product and variation configuration** — variation settings take precedence over the parent product.
- **Best-rule selection** — when several rules qualify, the largest calculated discount is used for the eligible cart line.
- **Scheduled promotions** — optional start and end dates use the WordPress site timezone.
- **Line-item protection** — a discount is capped at the eligible line total and cannot make that line negative.
- **Negative-fee presentation** — the combined discount is shown as a WooCommerce fee named `優惠折扣`.
- **Marketing message** — an optional message is rendered below the discount amount.
- **Jify Taxes integration data** — item-level discount amounts are stored in the WooCommerce session for tax-basis adjustment.
- **Fee ordering** — discount rows are sorted before tax rows when both are present.

## Calculation Model

The current implementation separates **eligibility** from **discount allocation**:

```text
WooCommerce merchandise subtotal
        │
        ├─ Check the configured threshold for each eligible product/variation
        │
        ├─ Calculate the best matching fixed or percentage rule
        │
        ├─ Cap the result at that cart line's total
        │
        ├─ Save item-level discount amounts in the WooCommerce session
        │
        └─ Add the combined amount as one negative fee
```

### Example

Assume a product has these rules:

| Cart threshold | Discount type | Value |
|---:|---|---:|
| NT$1,000 | Fixed | NT$100 |
| NT$2,000 | Percentage | 15% |

A cart has a merchandise subtotal of **NT$2,400**, including an eligible product line worth **NT$1,200**.

Both rules qualify for that line:

- Fixed rule: NT$100
- Percentage rule: NT$1,200 × 15% = NT$180

The plugin selects the larger result, so that line contributes **NT$180** to the combined cart discount.

## Important Behavior

### Fixed values are evaluated per eligible cart line

A fixed rule is not currently a single cart-wide discount. When multiple eligible lines share the same configuration, each line can contribute the configured fixed amount, subject to its line-total cap.

### Thresholds use the merchandise subtotal

Eligibility currently uses WooCommerce's `cart_contents_total`. Shipping is not included in the threshold calculation.

The administration UI stores an **Include Shipping** option, but the current calculation path does not use that value. Treat shipping-inclusive thresholds as unsupported until implementation and tests are added.

### Coupon interaction depends on store configuration

Jify Discount is represented as a negative fee. Standard WooCommerce coupons may therefore combine with it. The plugin does not currently provide an exclusivity, maximum-discount, or promotion-priority policy.

## Architecture

```text
Product or variation metadata
├── enabled flag
├── start and end date
├── marketing message
└── threshold:value:type rules

WooCommerce cart calculation
├── resolve variation or parent configuration
├── validate campaign date
├── evaluate qualifying rules
├── calculate item-level discounts
├── persist session data
└── add combined negative fee

Optional Jify integration
└── Jify Taxes reads item-level discount amounts from the session
```

## Installation

Requirements:

- WordPress 5.8 or newer
- PHP 7.4 or newer
- WooCommerce installed and active

Install the plugin:

1. Copy the repository to `/wp-content/plugins/jify-discount` or upload it through the WordPress Plugins screen.
2. Activate **Jify Discount**.
3. Open a product in WooCommerce.
4. Select **Product Data → Jify Discount**.
5. Enable the plugin for the product or variation and define one or more rules.

## Configuration

Each rule contains:

| Field | Purpose |
|---|---|
| Threshold | Minimum merchandise subtotal required to activate the rule |
| Value | Fixed discount amount or percentage |
| Type | `Fixed` or `Percent` |
| Start date | Optional first active date |
| End date | Optional final active date |
| Marketing message | Optional text displayed beside the applied discount |

## Manual Verification

Before using a rule in production, test at least these cases in a staging store:

1. Cart value just below the threshold.
2. Cart value exactly at the threshold.
3. Multiple qualifying thresholds.
4. Fixed and percentage rules on the same product.
5. Product variation overriding the parent configuration.
6. Multiple eligible cart lines.
7. Start and end dates in the configured WordPress timezone.
8. Coupon stacking.
9. Interaction with shipping and tax plugins.
10. Cart refresh, checkout refresh, and session expiration.

## Current Status

Version `2.1.0` is an early public release intended for controlled WooCommerce deployments. The core threshold, scheduling, product/variation, and negative-fee workflows are implemented.

Production stores should validate their complete promotion, tax, shipping, refund, and accounting behavior in staging before deployment.

## Known Limitations

- No automated test suite is included in the repository yet.
- The shipping-inclusive option is stored but not used by the current calculation path.
- There is no built-in coupon exclusivity or maximum cart discount setting.
- Fixed discounts apply per eligible cart line rather than once per cart.
- Rules are stored as a compact metadata string rather than a versioned schema.
- Refunds and order edits do not create an independent promotion audit ledger.
- Compatibility depends on how other extensions modify fees, subtotals, and checkout refresh behavior.

## Good Contribution Areas

Useful contribution areas include:

- PHPUnit and WooCommerce integration fixtures.
- Shipping-inclusive threshold support.
- Explicit cart-wide versus line-level rule modes.
- Promotion stacking and exclusivity policies.
- Structured rule storage and migration tooling.
- Promotion usage reporting and audit history.
- Import and export support.
- Translation and accessibility improvements.
- Compatibility testing across supported WooCommerce versions.

Please open an issue describing the expected behavior and a reproducible cart example before proposing a large behavioral change.

## Documentation

Product pages and the wider Jify plugin suite are available at [jify.cloud](https://jify.cloud).

The canonical WordPress plugin metadata lives in [`readme.txt`](readme.txt).

Related repositories:

- [Jify Shipping](https://github.com/yves01480/jify-shipping)
- [Jify Taxes](https://github.com/yves01480/jify-taxes)
- [Jify Loyalty](https://github.com/yves01480/jify-loyalty)
- [Jify Cloud Website](https://github.com/yves01480/jify-cloud-website)

## Ownership

I designed and implemented the promotion model, WooCommerce integration, product and variation configuration, fee ordering, session data sharing, and deployment workflow.

AI coding tools are used in parts of the engineering workflow. Product decisions, architecture, integration, review, deployment, and acceptance criteria remain my responsibility.

## License

GPL-2.0-or-later — see [`license.txt`](license.txt).
