# ColorMath #
Adds a parser hook for doing component adjustments to color values.

	{{#color:COLOR|...}}

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
This will try to output the color in the best possible fashion.

If it can output as a CSS color name, it will. Including "transparent".

If it has an alpha value, it will output rgba form.

Otherwise, it will output as a traditional hex form: #rrggbb

