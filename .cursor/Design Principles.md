
## Design System & UI/UX Standards

### Design Principles
- **Modern & Clean**: Prioritize whitespace, clear typography, and minimal visual clutter. Avoid busy layouts typical of legacy software.
- **User-Centric**: Design for task completion, not data display. Hide complexity; reveal progressively.
- **Consistency**: Establish and reuse UI patterns across all pages. Document any new patterns before implementing.

### Visual Foundation
- **Color System**: Define and document a limited color palette for:
  - Primary actions and branding
  - Secondary/supporting elements
  - Success, warning, and error states
  - Background and surface colors
  - Text hierarchy (headings, body, muted)
- Never introduce ad-hoc colors; extend the documented palette only when necessary.

### Visual Hierarchy
- **Typography**: Establish clear size/weight scale for headings, subheadings, body text, and supporting text.
- **Emphasis**: Each screen should have one clear primary action; secondary actions must be visually subordinate.
- **Spacing**: Use consistent spacing scale throughout (e.g., tight, normal, loose). Maintain rhythm between sections.

### Layout Standards
- **Containment**: Define consistent page widths and padding for content areas.
- **Grouping**: Related content must be visually grouped (cards, panels, sections with clear boundaries).
- **Alignment**: Maintain consistent alignment patterns (left-aligned text, centered modals, etc.).

### Component Behavior
- **Interactive States**: All interactive elements must have clear hover, focus, active, and disabled states.
- **Feedback**: Every user action requires immediate visual response (state changes, loading indicators, confirmations).
- **Errors & Validation**: Show errors contextually near their source with clear, actionable messages.
- **Empty States**: Never show empty tables/lists without helpful guidance and next action.
- **Loading States**: Use placeholders or indicators; avoid blank screens during async operations.

### User Experience Patterns
- **Progressive Disclosure**: Show essential information first; hide complexity behind clear affordances.
- **Destructive Actions**: Require explicit confirmation with clear consequence explanation.
- **Error Recovery**: Provide clear paths to resolve errors; avoid dead ends.
- **Responsiveness**: Design should ensure usability across device sizes.

### Accessibility Requirements
- Keyboard navigation must work for all interactive elements
- Color cannot be the only indicator of state or meaning
- Text must have sufficient contrast against backgrounds
- Use semantic HTML and appropriate ARIA labels

### Design Quality Gates
Before considering UI complete, verify:
- [ ] Follows established color palette and typography scale
- [ ] Has clear visual hierarchy with one primary action per view
- [ ] Includes all states: default, loading, empty, error, success
- [ ] Related content is properly grouped and visually separated
- [ ] Works on mobile, tablet, and desktop viewports
- [ ] Passes keyboard-only navigation test
- [ ] Matches established patterns from reference pages