create or replace view Q1(Name, Country) as 
select name, country from company where country not like 'Australia';

create or replace view Q2(Code) as 
select code, count(*) as freq
from  executive
group by code
having count(*) > 5;

create or replace view Q3(Name) as
select company.name from company, category where sector ilike 'technology' and (company.code=category.code);

create view Q4_helper as 
select a.industry, a.sector, count(*) from category a group by a.industry, a.sector order by sector;

create or replace view Q4(Sector, Number) as
select a.sector as sector, count(*) as number from Q4_helper as a group by a.sector;

create or replace view Q5_helper(Name) as
select company.name, company.code, category.sector from company, category where sector ilike 'technology' and (company.code=category.code);

create or replace view Q5(Name) as
select person, Q5_helper.code from executive, Q5_helper where (executive.code = Q5_helper.code);

create or replace view Q6(Name) as 
select company.name, company.zip, category.sector from company, category where sector ilike 'services' and (company.code=category.code) and (company.zip ilike '2%');

create view sortRow as select row_number() over (order by code), sorted.* from (select "Date", code, volume, price from asx order by code, "Date") as sorted;

create or replace view Q7("Date", Code, Volume, PrevPrice, Price, Change, Gain) as 
select b."Date", b.code, b.volume, a.price as PrevPrice, b.price as Price, (b.price-a.price) as change, (((b.price-a.price)/a.price)*100) as gain from sortRow as a, sortRow as b where (a.row_number+1 = b. row_number) and (a.code=b.code);

create view maxDates as select "Date", max(volume) from asx group by "Date" order by "Date";

create or replace view Q8("Date", Code, Volume) as
select maxDates."Date", code, max from asx, maxDates where asx.volume=maxDates.max;


create view sortSector as select distinct industry, sector from category order by sector, industry;

create or replace view Q9(Sector, Industry, Number) as select sortSector.sector ,sortSector.industry, count(code) from category inner join sortSector using (industry) group by sortSector.industry, sortSector.sector order by sector, sortSector.industry;


create or replace view Q10(Code, Industry) as 
select code, c.industry from (select * from Q9 where number =1) a, category c where (c.industry=a.industry) and (a.sector=c.sector) order by code;

 
create view joinRate as select * from category inner join rating using (code) order by sector;

create or replace view Q11(Sector, AvgRating) as
select sector, avg(star) as avgrating from (select distinct sector from category) as d inner join joinrate using (sector) group by sector;


create or replace view Q12(Name) as
select person from (select executive.code, executive.person from executive inner join company using (code)) as p group by person having count(person) > 1;


create view compcat as select * from company inner join category using (code);

create view foreignSector as select distinct sector from compcat where country not ilike 'Australia';


create or replace view Q13(Code, Name, Address, Zip, Sector) as
select Code, Name, Address, Zip, compcat.Sector from compcat left join foreignsector on compcat.sector=foreignsector.sector where foreignsector.sector is null;


create view minmaxASX as select "Date", code, price from asx, (select min("Date"), max("Date") from asx) as minmax where (asx."Date" = minmax.min) or (asx."Date" = minmax.max) order by code, "Date";

create or replace view Q14(Code, BeginPrice, EndPrice, Change, Gain) as
select a.Code, a.price as BeginPrice, b.price as EndPrice, (b.price-a.price) as change, (((b.price-a.price)/a.price)*100) as gain from minmaxasx as a, minmaxasx as b where (a."Date" < b."Date") and (a.code=b.code) order by gain desc, code asc;


create view test as select a.code, min(a.price), avg(a.price), max(a.price) from asx a, asx b where a.code = b.code group by a.code;

create view test2 as select a.code, min(a.gain) as mindaygain, avg(a.gain) as avgdaygain, max(a.gain) as maxdaygain from Q7 a, Q7 b where a.code = b.code group by a.code;

create or replace view Q15(Code, MinPrice, AvgPrice, MaxPrice, MinDayGain, AvgDayGain, MaxDayGain) as select * from test inner join test2 using (code);


create function checkAffiliated() returns trigger as $$    
	 declare updatecount INT;
begin
    select count(*) into updatecount from executive as a where(a.person = new.person);
	if (updatecount > 0) then 
    	raise exception 'Person is alrdy affiliated with a company';
    end if;
    return new;
end;
$$ language plpgsql;

create trigger insert_attempted
    before update or insert
    on Executive
    for each row execute procedure checkAffiliated();

create view sectorCompGain as select s.sector, Q15.* from category as s inner join Q15 using (code) order by sector; 

create view minmaxSectorGain as select a.sector, min(a.mindaygain) as minSectorGain , max(a.maxdaygain) as maxSectorGain from sectorcompgain as a, sectorcompgain as b where (a.sector=b.sector) group by a.sector;

create function checkGain() returns trigger as $$ 
    declare prevDate date;
    declare prevPrice float;
    declare newSector category;
    declare sectorGain minmaxSectorGain;
    declare newGain numeric;
begin 
    select max("Date") into prevDate from asx where code=new.code;
    select q7.price into prevPrice from Q7 as q7 where q7."Date" = prevDate and q7.code=new.code;
  
    if prevPrice = null then
        raise exception 'prevprice is null for (%)', new.code;    
    end if;

    newGain = (((new.price - prevPrice)/prevPrice)*100);
    select * into newSector from category as a where new.code=a.code; 
    select * into sectorGain from minmaxSectorGain where minmaxSectorGain.sector=newSector.sector;

    if (newGain = sectorGain.maxSectorGain) then 
        update rating set star = 5 from (select q.code from Q15 q where q.maxdaygain=newGain) as maxGain where (rating.code=maxGain.code);
        update rating set star = 5 where new.code=code;
    end if;

    if (sectorGain.maxSectorGain < newGain) then
        update rating set star = 5 where new.code=code;
    end if;

    if (sectorGain.minSectorGain > newGain) then  
        update rating set star = 1 where new.code=code;
    end if;
    
    if (newGain = sectorGain.minSectorGain) then  
		update rating set star = 1 from (select q.code from Q15 q where q.mindaygain=newGain) as minGain where (rating.code=minGain.code);
        update rating set star = 1 where new.code=code;
    end if;

    return new;
end;
$$ language plpgsql;

create trigger insert_new_asx
    before insert on asx
    for each row execute procedure checkGain();

create or replace function asxlog_record() 
    returns trigger as $$ 
    DECLARE old_record asx;
begin
    select * into old_record from asx as a where(a."Date" = new."Date") and (a.code=new.code);

    if new.price <> old_record.price or new.volume <> old_record.volume then 
    	insert into asxlog("Timestamp", "Date", code, OldVolume, OldPrice)
        values((select CURRENT_TIMESTAMP), new."Date", new.code, old_record.volume, old_record.price);
    end if;
    return new;
end;
$$ language plpgsql;

create trigger asx_changes
    before update
    on asx
    for each row
    execute procedure asxlog_record();

