with cond as (

    select 'Excellent' as vehicle_condition, 1 as sort_order union all
    select 'Very Good', 2 union all
    select 'Good', 3 union all
    select 'Fair', 4 union all
    select 'Rough', 5

),
vehicle as (

    select *
    from {{ ref('stg_vehicle') }}
)

select
    cond.vehicle_condition,
    round(avg(case when v.vehicle_type = 'Convertible' then v.purchase_price end), 2) as convertible,
    round(avg(case when v.vehicle_type = 'Coupe' then v.purchase_price end), 2) as coupe,
    round(avg(case when v.vehicle_type = 'Minivan' then v.purchase_price end), 2) as minivan,
    round(avg(case when v.vehicle_type = 'Sedan' then v.purchase_price end), 2) as sedan,
    round(avg(case when v.vehicle_type = 'SUV' then v.purchase_price end), 2) as suv,
    round(avg(case when v.vehicle_type = 'Truck' then v.purchase_price end), 2) as truck,
    round(avg(case when v.vehicle_type = 'Van' then v.purchase_price end), 2) as van,
    round(avg(case when v.vehicle_type = 'Other' then v.purchase_price end), 2) as other
from cond
left join vehicle v
    on cond.vehicle_condition = v.vehicle_condition
group by
    cond.vehicle_condition,
    cond.sort_order
order by
    cond.sort_order