# ColorMath #

Adds a parser hook for doing component adjustments to color values.

```mediawiki
{{#color:COLOR|...}}
```

## color ##

The color itself can be any valid CSS color value (it is in fact a little looser).

Below is a summary, using .. for inclusive range notation and % to indicate 0..100 followed by a literal percent sign.

* #rgb
* #rrggbb
* rgb(0..255, 0..255, 0..255)
* rgb(%, %, %)
* rgba(0..255, 0..255, 0..255, 0.0..1.0)
* rgba(%, %, %, %)
* hsl(0..360, %, %)
* hsla(0..360, %, %, 0.0..1.0)
* hsla(0..360, %, %, %)
* any valid CSS color name

## Arguments ##

You may specify either a command or a component modification.

### Command ###

There are currently only two commands *darken* and *lighten*.

If a CSS color name is specified, these will attempt to use the respective dark\* and light\* versions.

### Component modification ###

These are specified as keyword arguments of the form: `component=modification`

The components may be:

* red or r
* blue or b
* green or g
* hue or h
* saturation, sat, or s
* lightness, light, or l
* alpha or a

Modifications can either specify simply a number to reassign the component or otherwise add or subtract from it. These can be done in succession.
 It is also possible to specify another component as if it were a variable.

* Set green to 0: `g=0`
* Set lightness to 95%: `l=95%`
* Drop hue by 10: `h=-10`
* Make gray at red level: `g=r|b=r`

## Output ##

By default, this will try to output the color in the best possible fashion:

1. If it can output as a CSS color name, it will. Including "transparent".
2. If it has an alpha value, it will output rgba form.
3. Otherwise, it will output as a traditional hex form: #rrggbb

However you may override the output by specifying the `output` named parameter.

### Output forms ###

* `css` - The default output form, described above.
* `hex` - Output in the traditional hex form, dropping any alpha.
* `rgb` - Output in rgb or rgba like: `rgba(123, 222, 255, 0.8)`
* `rgb%` - Output in rgb or rgba using % like: `rgba(48%, 87%, 100%, 80%)`
* `hsl` - Output in hsl or hsla like: `hsla(195, 100%, 74.12%, 0.8)`
* `hsl%` - Output in hsl or hsla using % for alpha like: `hsla(195, 100%, 74.12%, 80%)`
* `format:...` - Output in a custom string format described below.

### Custom output format ###

This is like `sprintf` and similar formatting systems. Print any raw character until encountering a _conversion specification_ of one of the following forms, where anything surrounded by square brackets is optional:

* `%[component name$][flags][width][.precision]specifier`
* `%[(component name)][flags][width][.precision]specifier`
* `%{[component name:][flags][width][.precision]specifier}`

For example these are equivalent: `%red$>6.2f` and `%(red)>6.2f` and `%{red:$>6.2f}`

If the component name is not specified, the first time it will be `red`, the second it will be `green`, then `blue`, then finally `alpha`.

Component names may be anything listed [above](#Component-modification).

Flags can be one of the following:

| Flag    | Description                                                        |
| ------- | ------------------------------------------------------------------ |
| - or <  | Left-justify within the given field width                          |
| >       | Right-justify within the given field width (default)               |
| ^       | Center within the given field width (use with < to favor the left) |
| =       | Place padding between sign and number                              |
| +       | Prepend a + sign for positive numbers                              |
| (space) | Use a space as the sign for positive numbers                       |
| 0       | Pad with zeroes (by default, pad with space)                       |
| '(char) | Pad with the given character                                       |
| #       | Use with o, p, or x/X to affix with 0o, %, or 0x respectively      |

Width is an integer specifying the minimum number of characters to write out.

Precision is the exact number of digits to show after the decimal place.

Specifier may be one of the following:

| Specifier  | Description                                                            |
| ---------- | ---------------------------------------------------------------------- |
| %          | A literal percent sign                                                 |
| d, i, or u | Decimal value on the scale typically associated with this component    |
| f or F     | Floating point representation between 0 and 1 (default precision is 1) |
| o          | Octal representation of the integer value                              |
| p          | Percentage value                                                       |
| x          | Hexadecimal value with lowercase letters                               |
| X          | Hexdecimal value with uppercase letters                                |
