# Design Guide

Visual design reference for the SN-Fetcher admin interface. Based on Prototype 01 (Dark Sidebar).

## Layout

```
+-------------------+----------------------------------------------+
|                   |  Topbar (sticky, white)                      |
|  Sidebar (fixed)  +----------------------------------------------+
|  240px, dark      |                                              |
|  #1a1d23          |  Content area                                |
|                   |  padding: 24px 32px                          |
|  Navigation       |  background: #f0f2f5                         |
|  Brand + Links    |                                              |
|  User + Logout    |                                              |
+-------------------+----------------------------------------------+
```

- **Sidebar**: Fixed left, 240px wide, `#1a1d23` background
- **Topbar**: Sticky, white background, shows page title left + optional actions right
- **Content**: Right of sidebar, light gray `#f0f2f5` background
- **Login page**: No sidebar, centered card on `#f0f2f5` background

## Color System

### Base Colors

| Token | Value | Usage |
|-------|-------|-------|
| `--sidebar-bg` | `#1a1d23` | Sidebar background |
| `--sidebar-hover` | `#2a2d35` | Sidebar item hover |
| `--sidebar-text` | `#8b8fa3` | Sidebar text (inactive) |
| `--body-bg` | `#f0f2f5` | Main content background |
| `--card-shadow` | `0 1px 3px rgba(0,0,0,0.08)` | Card default shadow |
| `--card-shadow-hover` | `0 4px 12px rgba(0,0,0,0.12)` | Card hover shadow |

### Accent Colors

| Token | Value | Usage |
|-------|-------|-------|
| `--accent` | `#6366f1` | Primary accent (indigo), active sidebar item, primary buttons |
| `--accent-light` | `#818cf8` | Hover state for accent |
| `--success` | `#10b981` | Active/success states, toggle on |
| `--warning` | `#f59e0b` | Hidden items, warning states |
| `--danger` | `#ef4444` | Deleted items, destructive actions |

## Components

### Cards

Two card types:

1. **Stat Card** (`.stat-card`): White, rounded-12px, padding 20-24px. Icon top-right in colored circle (40x40px, 10px radius). Value large (28px, bold 700). Label 13px muted.

2. **Data Card** (`.data-card`): White, rounded-12px, shadow. Header with title and optional action buttons. Contains a data-table or custom content.

### Data Tables

Class: `.data-table` (NOT Bootstrap `.table`).

- Header: 11px uppercase, `#9ca3af` text, `#fafbfc` background
- Cells: 13px, 12px padding horizontal 24px
- Row hover: `#fafbfc` background
- Last row: no bottom border
- Deleted rows: `opacity: 0.5`
- Hidden rows: `#f8f9fa` background

### Network Badge

Class: `.net-badge`

```html
<span class="net-badge" style="background: rgba(99,100,255,0.1); color: #6364ff;">
    <i class="fab fa-mastodon"></i> Mastodon
</span>
```

Uses 10% opacity of network color as background, full color as text. Rounded-6px, padding 4-10px, 12px font, font-weight 600.

### Network Card

For dashboard grid. Rounded-12px, hover translateY(-2px) + shadow elevation. Header with 44px colored icon circle + name. Footer with stats in two columns separated by 1px border.

### Status Pills

Class: `.pill .pill-{variant}`

| Variant | Background | Text Color | Usage |
|---------|-----------|------------|-------|
| `.pill-success` | `#d1fae5` | `#065f46` | Active items |
| `.pill-danger` | `#fee2e2` | `#991b1b` | Deleted items |
| `.pill-warning` | `#fef3c7` | `#92400e` | Hidden items |
| `.pill-muted` | `#f3f4f6` | `#6b7280` | Inactive states |

### Action Buttons

Class: `.action-btn`

32x32px, transparent background, 6px radius, `#9ca3af` icon color. Hover: `#f3f4f6` background, darker icon. `.action-btn.danger:hover`: red tint.

### Toggle Switch

Keeps existing HTML structure (`label.toggle-switch > input + span.toggle-track`). Restyled: 36x20px track, 16px thumb, green `#10b981` when checked. Loading state with pulse animation preserved.

### Buttons

| Class | Style | Usage |
|-------|-------|-------|
| `.btn-primary-custom` | `#6366f1` bg, white text, 8px radius | Primary actions |
| Bootstrap `.btn-outline-*` | Keep for form buttons | Cancel, secondary |

### Sidebar Navigation

- Items: 14px, 500 weight, `#8b8fa3` text
- Hover: `#2a2d35` background
- Active: `#6366f1` background, white text
- Badge: right-aligned, 11px, pill shape
- Sections: 10px uppercase label, `rgba(139,143,163,0.5)` color

## Typography

- **Font**: Inter (Google Fonts), fallback: -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif
- **Stat values**: 28px, weight 700, letter-spacing -1px
- **Page titles** (topbar): 20px, weight 700, letter-spacing -0.5px
- **Section titles**: 16px, weight 600
- **Card titles**: 15px, weight 600
- **Body text**: 13px
- **Table headers**: 11px uppercase, letter-spacing 0.5px
- **Muted text**: `#9ca3af` or `#6b7280`
- **Time/date**: 12px, `#9ca3af`

## Icons

Font Awesome 6.5.1. Key mappings:

| Context | Icon |
|---------|------|
| Dashboard | `fa-th-large` |
| Networks | `fa-network-wired` |
| Profiles | `fa-users` |
| Items | `fa-stream` |
| Clients | `fa-id-badge` |
| View | `fa-eye` |
| Edit | `fa-pen` / `fa-edit` |
| Delete | `fa-trash` |
| Create | `fa-plus` |
| Save | `fa-save` |
| Hidden | `fa-eye-slash` |
| Fetch/Import | `fa-download` |
| External link | `fa-external-link-alt` |
| Logout | `fa-sign-out-alt` |
| Brand | `fa-rss` |

## Template Blocks

All page templates use these blocks:

```twig
{% block page_title %}Page Name{% endblock %}
{% block topbar_actions %}optional action buttons{% endblock %}
{% block body %}page content{% endblock %}
```

The `page_title` block populates the topbar title. The `topbar_actions` block places buttons in the topbar right side. The `body` block contains all page content.

## Responsive Behavior

- Below 768px: sidebar collapses (future enhancement)
- Bootstrap grid (`col-md-*`, `col-lg-*`) for content layouts
- Tables wrapped in `.table-responsive` for horizontal scroll
