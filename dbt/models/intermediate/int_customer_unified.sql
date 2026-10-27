with customers as ( 
    select * from {{ ref('stg_customer') }} 
), individuals as ( 
    select * from {{ ref('stg_individual') }} 
), businesses as ( 
    select * from {{ ref('stg_business') }} 
) 
select 
    c.customer_id, 
    case 
        when i.customer_id is not null then 'Individual' 
        when b.customer_id is not null then 'Business' 
        else 'Unknown' 
    end as customer_type, 
    coalesce(concat(i.first_name, ' ', i.last_name), b.business_name, concat('Customer ', c.customer_id)) as display_name, 
    i.first_name, 
    i.last_name, 
    b.business_name, 
    b.contact_first_name, 
    b.contact_last_name, 
    b.contact_title, 
    c.phone, 
    c.email, 
    c.street_address, 
    c.city, 
    c.state, 
    c.postal_code 
from customers c 
left join individuals i on c.customer_id = i.customer_id 
left join businesses b on c.customer_id = b.customer_id