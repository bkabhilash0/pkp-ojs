## Props

| Key | Description |
| --- | --- |
| `...` | Supports all props in [FieldBase](#/component/Form/fields/FieldBase). |
| `value` | The current value for this field. |
| `type` | Type of the `<input>` field. Either `checkbox` or `radio`. Default: `checkbox`. |
| `isOrderable` | Allow the user to reorder the options. Reordering will resort the items in the `value` property. Default: `false`. |
| `options` | An array of objects defining the options which can be selected. |

## Events

See [FieldBase](#/component/Form/fields/FieldBase).

## Usage

Use this component for selecting one or more options from a list. If the list of items is short, use this component instead of a [`FieldSelect`](#/component/Form/fields/FieldSelect).

## Confirm fields and boolean values

When using this component as a confirmation field, the `value` prop passed to the field should be `true` or `false`. This differs from the field's normal `value` type, which is an array.

## Orderable options

Pass the `isOrderable` prop when you want the order of options to be preserved. When options are reordered, their position in the `value` array will be updated.
