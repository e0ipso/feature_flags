# Console Output Examples for Screenshots

This document contains the actual console output and localStorage data for creating screenshots.

## Screenshot 6: Console Resolution Output

When executing `await Drupal.featureFlags.resolve('coin_flip')` in the browser console with Debug mode enabled, you should see:

```
[Feature Flags] Resolving flag: coin_flip
[Feature Flags] Context: {user_id: "anonymous-xxxxx"}
[Feature Flags] Evaluating algorithm: percentage_rollout (weight: 0)
[Feature Flags] Algorithm conditions: (none - catch-all)
[Feature Flags] Selected variant: Tails (uuid: bbbbbbbb-2222-2222-2222-222222222222)
[Feature Flags] Caching decision to localStorage

▼ FeatureFlagResult {
    ▼ featureFlag: FeatureFlagConfig {
        id: "coin_flip"
        label: "Coin Flip"
        ▼ variants: Array(3)
          0: {uuid: "aaaaaaaa-1111-1111-1111-111111111111", label: "Heads", value: "{\"result\": \"heads\", \"color\": \"#FFD700\"}"}
          1: {uuid: "bbbbbbbb-2222-2222-2222-222222222222", label: "Tails", value: "{\"result\": \"tails\", \"color\": \"#C0C0C0\"}"}
          2: {uuid: "cccccccc-3333-3333-3333-333333333333", label: "Edge", value: "{\"result\": \"edge\", \"color\": \"#FF6B6B\"}"}
        ▼ algorithms: Array(1)
          0: {uuid: "dddddddd-4444-4444-4444-444444444444", pluginId: "percentage_rollout", ...}
      }
    ▼ variant: {
        uuid: "bbbbbbbb-2222-2222-2222-222222222222"
        label: "Tails"
        value: "{\"result\": \"tails\", \"color\": \"#C0C0C0\"}"
      }
    ▼ result: {
        result: "tails"
        color: "#C0C0C0"
      }
  }
```

## Screenshot 7: localStorage Inspection

In the DevTools Application tab > Local Storage > https://drupal-contrib.ddev.site:

**Key:** `feature_flags:coin_flip`

**Value:**

```json
{
  "variantUuid": "bbbbbbbb-2222-2222-2222-222222222222",
  "timestamp": 1765610160870
}
```

**Explanation:**

- `variantUuid`: The UUID of the selected variant (Tails in this example)
- `timestamp`: Unix timestamp when the decision was cached
- This cached decision will be reused on subsequent page loads (when persistence is enabled)

## Alternative Variants

Depending on the random selection (based on percentages: 48% Heads, 48% Tails, 4% Edge), you might see:

### Heads Variant

```json
{
  "result": "heads",
  "color": "#FFD700"
}
```

### Edge Variant (rare - only 4%)

```json
{
  "result": "edge",
  "color": "#FF6B6B"
}
```

## Testing Different Outcomes

To see different outcomes:

1. Clear localStorage: `localStorage.removeItem('feature_flags:coin_flip')`
2. Reload the page
3. Run `await Drupal.featureFlags.resolve('coin_flip')` again
4. The deterministic hash may give you the same result, so you might need to:
   - Change user context
   - Modify the flag configuration temporarily
   - Test in incognito mode with different sessions
