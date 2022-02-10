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
if (count($argv) < 4) exit("not enough");

// Get the return results
if(count($argv) == 5){
    $str = '('.str_replace('&', '|', $argv[1]).')';
    $myArray = explode('&', $argv[1]);
    $num_genres = count($myArray);

    $q="select m.title, m.year, m.content_rating, m.lang, m.imdb_score, m.num_voted_users, g.movie_id, count(*)
    from
    (select * from genre g1 where g1.genre ~* %s
        group by g1.movie_id, g1.genre
        order by g1.movie_id)
    as g
    join(
        select m.id, m.title, m.year, m.content_rating, m.lang, r.imdb_score, r.num_voted_users
        from movie m
            inner join rating r on (r.movie_id = m.id)
        where m.year >= %d and m.year <= %d
        order by r.imdb_score desc, r.num_voted_users desc
    ) as m
    on (m.id = g.movie_id)
    group by m.title, m.year, m.content_rating, m.lang, m.imdb_score, m.num_voted_users, g.movie_id
    having count(*) = %d
    order by m.imdb_score desc, m.num_voted_users desc
    limit %d";

    $r = dbQuery($db, mkSQL($q, $str, $argv[3], $argv[4], $num_genres, $argv[2]));
    if (dbNResults($r) == 0) exit();
    $index = 1;
    $noRes = dbNResults($r);
    while ($t = dbNext($r)){
        $out = sprintf("%d. %s (%s, %s, %s) [%s, %s]", $index, $t["title"], $t["year"], $t["content_rating"], $t["lang"], $t["imdb_score"], $t["num_voted_users"]);
        echo("$out");
        if($index != $noRes) echo("\n");
        $index++;
    }

}elseif(count($argv) == 4){
    $q ="select m.title, m.year, m.content_rating, m.lang, r.imdb_score, r.num_voted_users
    from movie m
        inner join rating r on (r.movie_id = m.id)
    where m.year >= %d and m.year <= %d
    order by r.imdb_score desc, r.num_voted_users desc limit %d";
    $r = dbQuery($db, mkSQL($q, $argv[2], $argv[3], $argv[1]));
    if (dbNResults($r) == 0) exit();
    $index = 1;
    $noRes = dbNResults($r);
    while ($t = dbNext($r)){
        $out = sprintf("%d. %s (%s, %s, %s) [%s, %s]", $index, $t["title"], $t["year"], $t["content_rating"], $t["lang"], $t["imdb_score"], $t["num_voted_users"]);
        echo("$out");
        if($index != $noRes) echo("\n");
        $index++;
    }

}



?>
