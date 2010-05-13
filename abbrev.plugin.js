<?php
Header('Content-type', 'text/javascript');
?>
/*
 * Define an object for storing abbreviation information.
 */
function aDef(abbrev_p, def_p, caseful_p, prefix_p, postfix_p) {
    this.abbrev = abbrev_p;
    this.caseful = caseful_p;
    this.prefix = prefix_p;
    this.postfix = postfix_p;
    this.definition = def_p;
}
aDefs = [];
    
<?php
/*
 * Local Variables:
 * mode: C
 * c-file-style: "bsd"
 * tab-width: 4
 * indent-tabs-mode: nil
 * End:
 */
?>
