select * from orders where suborders_count <> (select count(order_id) from suborders where orders.id=suborders.order_id);
select SUM(total), count(id) from orders where id in (select order_id from suborders where orders.id=suborders.order_id
                                                                                       and count(distinct order_id) > 2)