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
if (count($argv) < 3) exit();

// Get the genres and keyword of the movie

    $q="select m.title, m.genres, k.keywords
        from (select m.title, m.year, string_agg(g.genre, ',') genres
        from movie m, genre g where m.title = %s and g.movie_id = m.id
        group by m.title, m.year
        order by m.year desc limit 1) as m
        join (select string_agg(distinct k.keyword, ',') keywords, m.title
        from movie m, keyword k
        where m.title = %s and k.movie_id=m.id group by m.title) as k
        on (m.title = k.title)
        group by m.title, m.genres, k.keywords";
    $r = dbQuery($db, mkSQL($q, $argv[1], $argv[1])); // need exact movie title
    if (dbNResults($r) == 0) exit();

    $movie = dbNext($r); //should have only one line
    $title = $movie["title"];
    $genres = '{'.$movie["genres"].'}';
    $kw = '{'.$movie["keywords"].'}';

    //need padding with coalesce wiht null shared keywords
    $q= "select
        m.title, m.year,
        cardinality(array_agg(g.genre::text)) as shared_genres,
        coalesce(cardinality(k.keywords),0) as shared_keywords,
        r.imdb_score, r.num_voted_users

        from movie m

        join rating r on m.id = r.movie_id
        join genre g on (m.id = g.movie_id) and g.genre = any(%s)

        left join (select movie_id, array_agg(keyword::text) as keywords
                    from   keyword k
                    where  keyword = any(%s)
                    group  by movie_id
        ) as k on (m.id = k.movie_id)
        group by m.title, m.year, r.imdb_score, r.num_voted_users, shared_keywords
        order by shared_genres desc,
        shared_keywords desc,
        r.imdb_score desc,
        r.num_voted_users desc
        limit %d";

    // all possible output with max similarities in genres, keywords, and sort by imdb & vote
    $index = 1;
    $r = dbQuery($db, mkSQL($q, $genres, $kw, ($argv[2]+1)));
    if (dbNResults($r) == 0) exit();

    $noRes = dbNResults($r);
    while($t = dbNext($r)){
        if(strcasecmp(strtolower($t["title"]), strtolower($argv[1])) != 0){
            $out = sprintf("%d. %s (%d) [%s, %s, %s, %s]", $index, $t["title"], $t["year"], $t["shared_genres"], $t["shared_keywords"], $t["imdb_score"], $t["num_voted_users"] );
            echo "$out";
            if($index != $noRes) echo("\n");
            $index +=1;
        }
    }

?>
