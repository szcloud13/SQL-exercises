#!/usr/bin/php
<?php

//
// pg - print all PG and PG-13 movies of a given year
//

// include the common PHP code file
require("a2.php");



// PROGRAM BODY BEGINS

$usage = "Usage: $argv[0] Actor Name";
$db = dbConnect(DB_CONNECTION);

// Check arguments
if (count($argv) < 2 || !is_string($argv[1])) exit("$usage\n");

// Get the return results
$actor_name = $argv[1];
$q =
"select m.title, m.year, m.content_rating, d.name, r.imdb_score
from actor, acting b
     inner join movie m on (m.id = b.movie_id)
     inner join rating r on (r.movie_id = b.movie_id)
     inner join director d on (d.id = m.director_id)
where actor.name = %s and actor.id = b.actor_id
order by m.year";
$r = dbQuery($db, mkSQL($q, $actor_name));
if (dbNResults($r) == 0) exit();

// Iterate through the results and print
$index = 1;
while ($t = dbNext($r)){
    $out = sprintf("%d. %s -- %s (%s, %s, %s)", $index, $t["title"], $t["name"], $t["year"], $t["content_rating"], $t["imdb_score"]);
    $index += 1;
    echo "$out\n";
}






?>
