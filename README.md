# pressbooks-svglatex-generator

Description: ETH eskript additions for Pressbooks

Version: n/a

Author: Stephan Müller

Copyright: © 2017, ETH Zurich, D-HEST, Stephan J. Müller

License: GPL-2.0+

License URI: http://www.gnu.org/licenses/gpl-2.0.txt


## Custom LaTeX Handler

Allows using a custom LaTeX image producer by defining ESCRIPT_LATEX_URL in wp-config.php. This feature can also be used to serve SVG instead of PNG images.

Formulas are adjusted to the current text color by requesting new images with the right color when needed. (See fixes.js.)

Implemented in components/latex.php.

### In a nutshell

Needs similar to pressbooks-eskript Plugin -->  pressbooks-eskript/components/latex.php

Change to wp-config.php --> define('ESCRIPT_LATEX_URL', 'https://eskript.ethz.ch/latex/?');








