sql

create database if not exists apoteka;
charset utf8mb4;
collate utf8mb4_general_ci; 
use apoteka;

create user if not exists 'apoteker'@'localhost' identified by 'admin123';
grant all privileges on apoteka.* to 'apoteker'@'localhost';        
flush privileges;


create table if not exists users (
    id int auto_increment primary key,
    username varchar(50) not null unique,
    password varchar(255) not null,
    role enum('admin', 'pharmacist') not null,
    created_at timestamp default current_timestamp
);

create table if not exists orders (
    id int auto_increment primary key,
    user_id int not null,
    order_date timestamp default current_timestamp,
    total_amount decimal(10, 2) not null,
    status enum('pending', 'completed', 'canceled') default 'pending',
    foreign key (user_id) references users(id)
);

create table if not exists products (
    id int auto_increment primary key,
    name varchar(100) not null,
    description text,
    price decimal(10, 2) not null,
    stock int not null,
    created_at timestamp default current_timestamp
);

create table if not exists order_items (
    id int auto_increment primary key,
    order_id int not null,
    product_id int not null,
    quantity int not null,
    price decimal(10, 2) not null,
    foreign key (order_id) references orders(id),
    foreign key (product_id) references products(id)
);  
