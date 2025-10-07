# IMPLEMENTATION_NOTES.md Summary
- Captures the position-based headway architecture: headway services in `src/Service/Headway`, immutable DTOs, console commands, and REST APIs that aim to compare vehicle crossings at a common reference point with timestamp fallbacks when data is incomplete.
- Documents key bug fixes (respecting `VehicleDto` immutability, adding `ScoreGradeEnum::NA`, correcting median calculations) and explains how they stabilize scoring accuracy.
- Details Saskatoon-specific data gaps where realtime and static GTFS trip IDs diverge, the resulting reliance on timestamp headways, and an assessment of available data sources.
- Notes architectural limits (no historical crossing storage) and outlines mitigation options ranging from waiting for feed alignment to implementing historical tracking or testing with another agency.
- Summarizes performance adjustments (higher PHP memory ceiling, bulk SQL inserts, cached direction lookups), production readiness, future enhancements, and test recommendations for validating when position-based scoring activates.
