# Feature Flags Module - Architecture Diagrams

This file contains Mermaid diagrams for use in the README and other documentation.

## Diagram 1: Client-Side Resolution Flow

```mermaid
sequenceDiagram
    autonumber
    participant Page as Page Load
    participant Settings as drupalSettings
    participant Code as Application Code
    participant Manager as FeatureFlagManager
    participant Event as Context Providers
    participant Algorithm as Decision Algorithm
    participant Storage as localStorage

    Page->>Settings: Attach feature flags config
    Code->>Manager: resolve('coin_flip')
    Manager->>Storage: Check cache (if persistence enabled)
    alt Cache Hit
        Storage-->>Manager: Cached variant
        Manager-->>Code: FeatureFlagResult (cached)
    else Cache Miss
        Manager->>Event: Fire 'featureFlags:provideContext'
        Event-->>Manager: Context {user_id, user_tier, ...}
        Manager->>Algorithm: evaluate(context)
        Algorithm->>Algorithm: Check conditions
        Algorithm->>Algorithm: Execute decide()
        Algorithm-->>Manager: Selected variant
        Manager->>Storage: Cache decision (if persistence enabled)
        Manager-->>Code: FeatureFlagResult (fresh)
    end
```

## Diagram 2: Integration Architecture

```mermaid
graph TB
    subgraph "Feature Management Ecosystem"
        FF[feature_flags Module]
        AB[ab_tests Module]
    end

    subgraph "Application Layer"
        Code[Application Code]
    end

    subgraph "feature_flags Responsibilities"
        FF_R["Feature Rollouts<br/>Variant Selection<br/>Client-Side Resolution<br/>Percentage Distribution"]
    end

    subgraph "ab_tests Responsibilities"
        AB_R["Experiment Tracking<br/>Metrics Collection<br/>Statistical Analysis<br/>Conversion Analytics"]
    end

    Code -->|"resolve() flags"| FF
    Code -->|"track() experiments"| AB
    FF -.->|"provides variants for"| AB_R
    AB -.->|"informs rollout %"| FF_R

    style FF fill:#e1f5ff
    style AB fill:#f0f0ff
    style Code fill:#fff4e1
    style FF_R fill:#e1f5ff
    style AB_R fill:#f0f0ff
```

## Diagram 3: Decision Matrix Flowchart

```mermaid
flowchart TD
    Start([Need to control<br/>feature visibility?])
    Start --> Q1{Need to track<br/>experiment metrics?}

    Q1 -->|Yes| Q2{Need gradual<br/>rollout control?}
    Q1 -->|No| Q3{Need percentage<br/>distribution?}

    Q2 -->|Yes| Both[Use BOTH<br/>feature_flags + ab_tests]
    Q2 -->|No| ABOnly[Use ab_tests only<br/>for experiments]

    Q3 -->|Yes| FFOnly[Use feature_flags only<br/>for rollouts]
    Q3 -->|No| Simple[Use simple<br/>config or env vars]

    Both --> Example1["Example: New checkout flow<br/>• feature_flags: 25% rollout<br/>• ab_tests: track conversions"]
    ABOnly --> Example2["Example: Button color test<br/>• ab_tests: A/B test tracking<br/>• No gradual rollout needed"]
    FFOnly --> Example3["Example: Beta feature<br/>• feature_flags: user tier targeting<br/>• No metrics tracking needed"]

    style Both fill:#c8e6c9
    style ABOnly fill:#fff9c4
    style FFOnly fill:#b3e5fc
    style Simple fill:#ffccbc
```

## Usage in README

To embed these diagrams in the README, copy the Mermaid code blocks directly into the markdown file.
The diagrams will render automatically on GitHub.
