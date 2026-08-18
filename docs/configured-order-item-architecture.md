# Future configured order items

A future `ConfiguredOrderItem` must not require a Sylius Product or ProductVariant. Its immutable `ConfigurationSnapshot` stores the configurator code and name, quantity, selection and price-breakdown snapshots, total minor units, currency, channel, configuration hash, and snapshot version. Orders therefore remain reproducible after catalog edits or configurator deletion.
