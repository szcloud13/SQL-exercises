#!/usr/bin/php
<?php

//
// pg - print all PG and PG-13 movies of a given year
//

// include the common PHP code file
require("a2.php");


// PROGRAM BODY BEGINS

$usage = "Usage: $argv[0]";

$db = dbConnect(DB_CONNECTION);

// Check arguments
if (count($argv) < 4) exit();
 $q = "select distinct actor_id from movieactor m where m.name ~* %s";
 $r = dbQuery($db, mkSQL($q, $argv[1]));
 if (dbNResults($r) == 0) exit();
 $t = dbNext($r);
 $actor_id = $t["actor_id"];


 $q = "with recursive tc as(
     select %s::varchar as name, %d as actor_id, 0 as level
  union
     select a2.name, a2.actor_id, level+1
            from movieactor a1, movieactor a2, tc
            where a1.movie_id = a2.movie_id and tc.actor_id = a1.actor_id
     and tc.level < %d
  )
  select distinct name, level from tc order by level, name";
  $r = dbQuery($db, mkSQL($q, $argv[1], $actor_id, $argv[3]));
  if (dbNResults($r) == 0) exit();
  $noRes = dbNResults($r);
  echo("$noRes\n");
$index = 1;
 while ($t = dbNext($r)){
     if($t["level"] >= $argv[2] && $t["level"] <= $argv[3]){
         if(strcasecmp($t["name"], $argv[1]) != 0){
             $out = sprintf("%d. %s (%d)", $index, $t["name"], $t["level"]);
             echo "$out\n";
             $index += 1;
         }
     }
 }




?>

with recursive tc as(
    select 'chris evans'::varchar as name, 1598 as actor_id, 0 as level
 union
    select a2.name, a2.actor_id, level+1
           from movieactor a1, movieactor a2, tc
           where a1.movie_id = a2.movie_id and tc.actor_id = a1.actor_id
    and tc.level < 3
 )
 select distinct name, level from tc order by level, name
