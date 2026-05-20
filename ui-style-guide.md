# UC CCS Smart Sit-In Monitoring System: UI Style Guide

This document outlines the visual identity, color palette, typography, and component specifications for the redesign of the UC CCS Sit-In Monitoring System.

## 🎨 Color Palette

### Backgrounds
*   **Primary Background:** `#0D0B1A` (Deep near-black dark purple)
*   **Card Background (Purple Variant):** `#1A1530` (Dark muted purple)
*   **Card Background (Brown/Gold Variant):** `#1E1208` (Very dark amber/brown)

### Accents
*   **Primary Accent (Purple):** `#8B3FD9` (Vivid medium purple)
*   **Secondary Accent (Purple):** `#7B2FBE` (Slightly darker purple)
*   **Primary Accent (Gold/Amber):** `#D4870A` (Warm golden orange)
*   **Secondary Accent (Gold/Amber):** `#E09B1A` (Brighter golden orange)

### Typography Colors
*   **Hero & Section Headings:** `#C084FC` to `#A855F7` (Bright lavender-purple gradient/solid)
*   **Body Text:** `#D1C7E0` (Light muted lavender-white)
*   **Subtext / Descriptions:** `#9A8FB0` (Medium muted lavender-gray)

### UI Elements
*   **Borders:** `rgba(139, 63, 217, 0.3)` (Subtle purple border for cards/containers)

---

## 🔤 Typography

### Font Families
*   **Primary Font (Headings):** `Orbitron`, `Cinzel`, or a bold tech/gaming aesthetic display font.
    *   *Usage:* Hero title, Section titles, Card titles, Role names, Footer brand name.
*   **Secondary Font (Body/UI):** `Inter`, `Poppins`, or `Roboto` (Clean sans-serif).
    *   *Usage:* Descriptions, button labels, navigation items, list items.

### Font Sizes & Weights
*   **Hero Heading:** `52px` - `60px`, Bold (Purple gradient or solid)
*   **Section Headings:** `36px` - `42px`, Bold, Centered
*   **Card Titles:** `18px` - `20px`, Medium/Bold (White or Gold depending on card variant)
*   **Body Text:** `14px` - `16px`, Regular/Light
*   **Small Labels / Pills:** `11px` - `13px`, Regular or Uppercase

---

## 🧱 Layout & Grid

*   **Max Content Width:** `900px` - `960px` (Centered with outer padding)
*   **Hero Section:** 2-column layout (Left: Text + CTAs + Badges | Right: Large Shield Logo Emblem `~260-300px`)
*   **Feature Cards:** 2-column grid, equal width, rounded corners (`12px` - `16px` border radius)
*   **Access Level Cards:** 3-column grid, equal width, centered content
*   **Vertical Spacing:** Generous padding between major sections (`80px` - `100px`)
*   **Card Internal Padding:** `24px` - `32px`

---

## 🔘 Component Specifications

### 1. Navigation Bar
*   **Background:** Dark (transparent or matching primary bg `#0D0B1A`), no border/shadow.
*   **Logo (Left):** Shield icon (Gold) + "SIT-IN MONITORING" (White, Bold) + Subtitle (Muted Gray).
*   **Actions (Right):**
    *   **Login Button:** Outline style, white text, rounded `~20px`.
    *   **Register Button:** Solid Gold/Amber fill (`#D4870A`), white text, rounded `~20px`.

### 2. Buttons & Badges (Hero Section)
*   **Badge/Pill:** Background `#1A1530`, purple left border, white text ("SIT-IN MONITORING 🛡"), small shield icon.
*   **Login CTA:** Solid Gold/Amber (`#D4870A`), white text, left icon, rounded `8px`, height `48px`.
*   **Create Account CTA:** Dark outlined (transparent bg, white border), white text, left icon, rounded `8px`, height `48px`.
*   **Hover States:** Slightly lighten or add a soft glow in the respective accent color.

### 3. Feature Cards
*   **Style:** Rounded corners (`14px`), subtle border `rgba(139, 63, 217, 0.3)`, no heavy drop shadow (flat dark surfaces with subtle glow).
*   **Purple Variant:** `#1A1530` bg, purple icon container (`40x40px`, rounded square), white title, muted gray description.
*   **Gold/Brown Variant:** `#1E1208` bg, gold icon container (`40x40px`, rounded square), gold title, muted gray description.
*   **Icons:** `22px` - `26px`, white, placed inside the colored containers.

### 4. Access Level Cards
*   **Background:** Slightly lighter dark tone than main background (`#1A1530` or similar).
*   **Layout:** Centered content. Large circular icon container (`64px`) at top (Purple for Student/Admin, Gold for Faculty).
*   **Checklist:** Left-aligned, `~14px` white text with Purple or Gold checkmarks (✓).
*   **Borders/Radius:** Subtle border, rounded corners.

### 5. Footer
*   **Background:** Same as main background (`#0D0B1A`).
*   **Layout (3-columns):**
    *   **Left:** Shield icon + "UC CCS" (Gold Display Font), italic tagline, "Established 1983".
    *   **Center:** "Quick Links" (Gold), plain muted white text links.
    *   **Right:** "Contact" (Gold), address lines in muted white.

---

## 🖼️ Iconography & Visual Style Notes

*   **Icon Style:** Outlined or filled minimal icons (Lucide, Heroicons, or FontAwesome).
*   **Aesthetic:** *Dark Academic + Tech/Gaming Hybrid*.
*   **Lighting/Depth:** Avoid heavy gradients. Use flat dark surfaces with selective accent colors. Apply slight purple glows or tinted borders on cards to suggest depth without relying on traditional drop shadows.
*   **Vibe:** Authoritative, secure, and prestigious, yet modern and approachable.
