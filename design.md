---
name: clear-navy-design-system
source: derived
character: "A calm, clear catalog with generous product photography, crisp navy hierarchy, and a practical administration surface. The same visual language connects shopping and management without making them feel like the same page."
tokens: "Values live in resources/css/app.css. Read them there, do not duplicate them here."
contrast: "Body 8.92:1 light and 12.38:1 dark. Muted 5.34:1 light and 9.45:1 dark. Text on accent 11.52:1 light and 8.83:1 dark. Control border 3.78:1 light and 3.94:1 dark."
---

## Build mandate

Build a complete, branded product surface with useful copy, a clear content hierarchy, and loading, empty, and error states. Do not leave a page as a bare form or table. Use the UI guide's accessibility bar for every interaction.

## Character and direction

The store feels like a considered catalog. Large images and generous space give products room, while navy anchors headings and actions. The administration uses the same colors and type with tighter tables and forms.

## Composition patterns

The store uses a compact header, a clear page introduction, one primary content region, and a quiet footer when the page needs one. Product listings use large image cards and readable filters. Detail pages put the image and product facts first. Administration uses a responsive navigation area, a page title with actions, and grouped content panels.

## Component and usage rules

Use the CSS variables in `resources/css/app.css` as the single source for color, type, spacing, borders, radius, shadow, and motion. Keep the navy accent for important actions and links. Use surfaces and borders to group content, with shadows reserved for raised panels. Every control needs a visible focus state. Never communicate a state with color alone.

## Responsive and accessibility direction

Start at phone width with one product column. Add columns on wider screens. Keep controls touch friendly and prevent the page from overflowing horizontally. Set the page language and direction from the selected locale. Support the device color preference, then preserve an explicit user choice. Follow WCAG 2.2 AA for contrast, keyboard use, focus, labels, and semantic structure.
