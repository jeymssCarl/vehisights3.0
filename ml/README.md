# VehiSights RFC prediction pipeline

VehiSights uses a `RandomForestClassifier` to predict the hourly Level of Service (LOS) class from `A` to `F`. Training features include the road, direction, record source, Manila-local hour, weekday, month, day of year, weekend flag, and cyclical hour/weekday values.

The classifier does not perform numeric regression. For each predicted LOS, the application estimates V/C from matching historical medians in this order: road + hour + LOS, road + LOS, global LOS, then the standard LOS midpoint. Estimated PCU is calculated from that V/C estimate and the road capacity. The confidence stored with each forecast is the classifier probability for that individual result.

The current artifact schema is `rfc-los-v2`. Model and metadata filenames are intentionally different from the former hybrid artifacts, so an old regressor/classifier model cannot be reused accidentally. Retraining is required after upgrading.
