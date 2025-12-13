# Screenshots Directory

This directory contains screenshots for the Feature Flags module README.

## Required Screenshots

The following screenshots were captured during the Visual README Overhaul project:

### Admin Interface Screenshots

1. **01-settings-page.png** - Settings page at `/admin/config/services/feature-flags`
   - Shows Debug mode and Persist decisions checkboxes (both enabled)
   - Demonstrates global feature flags configuration

2. **02-feature-flags-list.png** - Feature flags list page
   - Shows the coin_flip flag in the list
   - Displays columns: Label, Machine name, Status, Variants, Algorithms, Operations

3. **03-edit-coin-flip-basic.png** - Basic Information tab
   - Label: "Coin Flip"
   - Machine name: coin_flip
   - Description filled in
   - Enabled checkbox checked

4. **04-variants-all-three.png** - Variants tab showing all three variants
   - Heads: `{"result": "heads", "color": "#FFD700"}`
   - Tails: `{"result": "tails", "color": "#C0C0C0"}`
   - Edge: `{"result": "edge", "color": "#FF6B6B"}`
   - CodeMirror JSON editor visible

5. **05-decision-algorithms.png** - Decision Algorithms tab
   - Percentage Rollout algorithm configured
   - Heads: 48%
   - Tails: 48%
   - Edge: 4%
   - Conditions section (empty - catch-all)

### Console and Storage Screenshots (Phase 2)

6. **06-console-resolution.png** - Browser console showing resolution
   - Debug logs from resolving coin_flip flag
   - Context values
   - Selected variant output
   - FeatureFlagResult object structure

7. **07-localstorage-persistence.png** - localStorage inspection
   - Key: `feature_flags:coin_flip`
   - Cached decision data visible

## Screenshot Specifications

- **Width**: 1200px minimum
- **Format**: PNG
- **Naming**: Numbered sequence with descriptive name
- **Quality**: Optimized for web while maintaining readability

## Notes

Screenshots were captured using Puppeteer MCP but need to be manually exported.
The coin_flip feature flag configuration is available in `/config/sync/feature_flags.feature_flag.coin_flip.yml`.
