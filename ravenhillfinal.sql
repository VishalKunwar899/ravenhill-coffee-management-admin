-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Aug 23, 2025 at 02:40 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ravenhill_final`
--
Create Database ravenhill_final;
CREATE DATABASE IF NOT EXISTS ravenhill_final;
USE ravenhill_final;

-- Drop tables if they exist
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `admin`;
DROP TABLE IF EXISTS `cashier`;
DROP TABLE IF EXISTS `category`;
DROP TABLE IF EXISTS `customer`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `inventory_transaction`;
DROP TABLE IF EXISTS `loyalty_program`;
DROP TABLE IF EXISTS `notification`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `order_item`;
DROP TABLE IF EXISTS `payment`;
DROP TABLE IF EXISTS `product`;
DROP TABLE IF EXISTS `promotion`;
DROP TABLE IF EXISTS `review`;
DROP TABLE IF EXISTS `staff`;
DROP TABLE IF EXISTS `supplier`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS=1;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` varchar(50) NOT NULL,
  `access_granted_on` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cashier`
--

CREATE TABLE `cashier` (
  `cashier_id` varchar(50) NOT NULL,
  `cashier_code` varchar(50) DEFAULT NULL,
  `shift` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `category_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` varchar(50) NOT NULL,
  `loyalty_points` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` varchar(50) NOT NULL,
  `product_id` INT DEFAULT NULL,
  `stock_level` int(11) DEFAULT NULL,
  `supplier_id` varchar(50) DEFAULT NULL,
  `threshold` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transaction`
--

CREATE TABLE `inventory_transaction` (
  `transaction_id` varchar(50) NOT NULL,
  `inventory_id` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `transaction_time` date DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_program`
--

CREATE TABLE `loyalty_program` (
  `loyalty_id` varchar(50) NOT NULL,
  `customer_id` varchar(50) DEFAULT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `notification_id` varchar(50) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `notif_type` varchar(50) DEFAULT NULL,
  `content` varchar(255) DEFAULT NULL,
  `sent_time` datetime DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` varchar(50) NOT NULL,
  `customer_id` varchar(50) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `cashier_id` varchar(50) DEFAULT NULL,
  `total_price` float DEFAULT NULL,
  `order_time` datetime DEFAULT NULL,
  `order_type` varchar(20) DEFAULT NULL,
  `order_address` varchar(255) DEFAULT NULL,
  `promotion_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_item`
--

CREATE TABLE `order_item` (
  `order_item_id` varchar(50) NOT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `product_id` INT DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `unit_price` float DEFAULT NULL,
  `customisations` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `payment_id` varchar(50) NOT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `amount` float DEFAULT NULL,
  `method` varchar(50) DEFAULT NULL,
  `payment_status` varchar(20) DEFAULT NULL,
  `payment_time` datetime DEFAULT NULL,
  `cashier_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `product_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `price` float DEFAULT NULL,
  `loyalty_points` int(11) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `available` tinyint(1) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `allergens` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promotion`
--

CREATE TABLE `promotion` (
  `promotion_id` varchar(50) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `value` float DEFAULT NULL,
  `start` date DEFAULT NULL,
  `end` date DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `review`
--

CREATE TABLE `review` (
  `review_id` varchar(50) NOT NULL,
  `customer_id` varchar(50) DEFAULT NULL,
  `product_id` INT DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `approved` tinyint(1) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` varchar(50) NOT NULL,
  `staff_number` varchar(50) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `supplier_id` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `cashier`
--
ALTER TABLE `cashier`
  ADD PRIMARY KEY (`cashier_id`),
  ADD UNIQUE KEY `cashier_code` (`cashier_code`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `inventory_transaction`
--
ALTER TABLE `inventory_transaction`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `loyalty_program`
--
ALTER TABLE `loyalty_program`
  ADD PRIMARY KEY (`loyalty_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `cashier_id` (`cashier_id`),
  ADD KEY `promotion_id` (`promotion_id`);

--
-- Indexes for table `order_item`
--
ALTER TABLE `order_item`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `cashier_id` (`cashier_id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `promotion`
--
ALTER TABLE `promotion`
  ADD PRIMARY KEY (`promotion_id`);

--
-- Indexes for table `review`
--
ALTER TABLE `review`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `staff_number` (`staff_number`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `cashier`
--
ALTER TABLE `cashier`
  ADD CONSTRAINT `cashier_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `customer`
--
ALTER TABLE `customer`
  ADD CONSTRAINT `customer_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`),
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`);

--
-- Constraints for table `inventory_transaction`
--
ALTER TABLE `inventory_transaction`
  ADD CONSTRAINT `inventory_transaction_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`),
  ADD CONSTRAINT `inventory_transaction_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `loyalty_program`
--
ALTER TABLE `loyalty_program`
  ADD CONSTRAINT `loyalty_program_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  ADD CONSTRAINT `loyalty_program_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `notification_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`cashier_id`) REFERENCES `cashier` (`cashier_id`),
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`promotion_id`) REFERENCES `promotion` (`promotion_id`);

--
-- Constraints for table `order_item`
--
ALTER TABLE `order_item`
  ADD CONSTRAINT `order_item_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_item_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`);

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `payment_ibfk_2` FOREIGN KEY (`cashier_id`) REFERENCES `cashier` (`cashier_id`);

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`),
  ADD CONSTRAINT `product_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `review`
--
ALTER TABLE `review`
  ADD CONSTRAINT `review_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  ADD CONSTRAINT `review_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`);

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`user_id`);

--
-- Insert data for categories
--

INSERT INTO `category` (`name`) VALUES 
('Coffee'), 
('Drinks'), 
('Breakfast'), 
('Lunch'), 
('Sides'), 
('Pastries'),
('Kids'),
('Promotions');

--
-- Insert data for products
--

INSERT INTO `product` (`name`, `description`, `price`, `image_url`, `category_id`, `available`, `allergens`) VALUES
('Affogato', 'Espresso over vanilla ice cream.', 6.50, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 1, 1, 'Dairy'),
('Babycino', 'Frothy milk for kids.', 3.00, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 1, 1, 'Dairy'),
('Cappuccino', 'Espresso with equal parts milk and foam.', 5.50, 'https://images.unsplash.com/photo-1572442388796-11668a67eaf3?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 1, 1, 'Dairy'),
('Flat White', 'Iconic Aussie coffee: espresso with microfoam milk.', 5.50, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 1, 1, 'Dairy'),
('Iced Coffee', 'Chilled coffee with milk and ice cream.', 7.00, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 1, 1, 'Dairy'),
('Latte', 'Smooth espresso with steamed milk.', 5.50, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 1, 1, 'Dairy'),
('Long Black', 'Espresso over hot water, strong and bold.', 5.00, 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 1, 1, 'None'),
('Magic', 'Melbourne specialty: double ristretto in 3/4 milk.', 6.00, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 1, 1, 'Dairy'),
('Mocha', 'Espresso with chocolate and milk.', 6.50, 'https://images.unsplash.com/photo-1510590337768-859d3a7c7a5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 1, 1, 'Dairy'),
('Piccolo Latte', 'Small latte with strong espresso.', 5.00, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 1, 1, 'Dairy'),
('Ristretto', 'Short, intense espresso shot.', 4.50, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 1, 1, 'None'),
('Short Black', 'Single shot of espresso.', 4.50, 'Images/es.jpeg', 1, 1, 'None'),
('Chai Latte', 'Spiced tea with milk.', 5.50, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 2, 1, 'Dairy'),
('Hot Chocolate', 'Creamy hot chocolate.', 5.50, 'https://images.unsplash.com/photo-1542990253-369dd4e91843?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 2, 1, 'Dairy'),
('Iced Tea', 'Refreshing brewed iced tea.', 4.50, 'https://images.unsplash.com/photo-1558642790-676bb3b166f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 2, 1, 'None'),
('Lemonade', 'Fresh squeezed lemonade.', 5.00, 'https://images.unsplash.com/photo-1621265649800-0c05aa31ceab?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 2, 1, 'None'),
('Matcha Latte', 'Green tea latte.', 6.00, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 2, 1, 'Dairy'),
('Smoothie', 'Berry blend smoothie.', 7.00, 'https://images.unsplash.com/photo-1505252585461-04db1eb84625?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 2, 1, 'Dairy'),
('Turmeric Latte', 'Golden milk with spices.', 6.00, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 2, 1, 'Dairy'),
('Big Brekkie', 'Eggs, bacon, sausage, toast, mushrooms, tomato.', 22.00, 'https://images.unsplash.com/photo-1528207776459-edd2c0f1e23a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 3, 1, 'Gluten,Eggs'),
('Chilli Labneh Eggs', 'Eggs with labneh and chilli oil.', 17.50, 'https://images.unsplash.com/photo-1528207776459-edd2c0f1e23a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 3, 1, 'Dairy,Eggs'),
('Chorizo & Eggs', 'Spicy chorizo with scrambled eggs and greens.', 19.00, 'https://images.unsplash.com/photo-1528207776459-edd2c0f1e23a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 3, 1, 'Eggs'),
('Coconut Chia Pudding', 'Chia with coconut milk and fruits.', 14.00, 'https://images.unsplash.com/photo-1528207776459-edd2c0f1e23a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 3, 1, 'None'),
('Eggs Benedict', 'Poached eggs with hollandaise on muffin.', 20.00, 'https://images.unsplash.com/photo-1528207776459-edd2c0f1e23a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 3, 1, 'Dairy,Gluten,Eggs'),
('Omelette', 'Cheese, ham, and veggie omelette.', 16.00, 'https://images.unsplash.com/photo-1528207776459-edd2c0f1e23a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 3, 1, 'Dairy,Eggs'),
('Pancakes', 'Fluffy stack with maple syrup and berries.', 18.50, 'https://images.unsplash.com/photo-1528207776459-edd2c0f1e23a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 3, 1, 'Dairy,Gluten,Eggs'),
('Smashed Avocado Toast', 'Iconic Aussie brekkie: avo on sourdough with poached egg.', 18.00, 'https://images.unsplash.com/photo-1482049016688-2d3e1ac02b8f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 3, 1, 'Gluten,Eggs'),
('Burger', 'Beef burger with beetroot (Aussie style).', 20.00, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 4, 1, 'Gluten,Dairy'),
('Chicken Caesar Salad', 'Grilled chicken with Caesar dressing.', 18.00, 'https://images.unsplash.com/photo-1550305062-29b4c46a9254?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 4, 1, 'Dairy,Eggs'),
('Club Sandwich', 'Turkey, bacon, and salad layers.', 16.50, 'https://images.unsplash.com/photo-1521305916504-4a11211852fe?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 4, 1, 'Gluten'),
('Fish and Chips', 'Battered fish with chips.', 22.00, 'https://images.unsplash.com/photo-1573088694543-1f19a28c42d6?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 4, 1, 'Gluten'),
('Meat Pie', 'Classic Aussie pie with gravy.', 12.00, 'https://images.unsplash.com/photo-1521305916504-4a11211852fe?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 4, 1, 'Gluten'),
('Sausage Roll', 'Flaky pastry with sausage.', 8.00, 'https://images.unsplash.com/photo-1521305916504-4a11211852fe?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 4, 1, 'Gluten'),
('Vegemite Toastie', 'Vegemite and cheese grilled sandwich.', 10.00, 'https://images.unsplash.com/photo-1521305916504-4a11211852fe?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 4, 1, 'Gluten,Dairy'),
('Veggie Wrap', 'Hummus and veggies in a wrap.', 14.00, 'https://images.unsplash.com/photo-1610890716324-2b86e1492e31?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 4, 1, 'Gluten'),
('Fries', 'Crispy french fries.', 6.00, 'https://images.unsplash.com/photo-1573088694543-1f19a28c42d6?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 5, 1, 'None'),
('Garlic Bread', 'Toasted garlic bread.', 6.50, 'https://images.unsplash.com/photo-1521305916504-4a11211852fe?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 5, 1, 'Gluten,Dairy'),
('Hash Browns', 'Crispy potato hash browns.', 5.00, 'https://images.unsplash.com/photo-1521305916504-4a11211852fe?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 5, 1, 'None'),
('Onion Rings', 'Battered onion rings.', 7.00, 'https://images.unsplash.com/photo-1615486364740-4a9c742d9b0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 5, 1, 'Gluten'),
('Side Salad', 'Mixed greens with dressing.', 5.50, 'https://images.unsplash.com/photo-1512621770439-720e57f8c09b?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 5, 1, 'None'),
('ANZAC Biscuit', 'Oat and coconut biscuit.', 3.50, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 6, 1, 'Gluten'),
('Croissant', 'Flaky butter croissant.', 4.50, 'Images/cross.jpeg', 6, 1, 'Dairy,Gluten'),
('Danish', 'Apple danish pastry.', 5.00, 'https://images.unsplash.com/photo-1541782017295-98b2d536f5e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 6, 1, 'Dairy,Gluten'),
('Lamington', 'Chocolate-coated sponge with coconut.', 5.00, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 6, 1, 'Gluten,Dairy'),
('Muffin', 'Blueberry muffin.', 4.50, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 6, 1, 'Dairy,Gluten,Eggs'),
('Scone', 'Fresh scone with jam and cream.', 4.00, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 6, 1, 'Dairy,Gluten'),
('Tim Tam', 'Chocolate biscuit (pack of 2).', 3.00, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 6, 1, 'Gluten,Dairy'),
('Vanilla Slice', 'Custard-filled pastry slice.', 5.50, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 6, 1, 'Dairy,Gluten,Eggs'),
('Kids Babycino', 'Frothy milk with sprinkles.', 3.00, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 7, 1, 'Dairy'),
('Kids Cheese Toastie', 'Grilled cheese sandwich.', 6.00, 'https://images.unsplash.com/photo-1521305916504-4a11211852fe?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 7, 1, 'Gluten,Dairy'),
('Kids Fruit Cup', 'Fresh fruit pieces.', 4.50, 'https://images.unsplash.com/photo-1528207776459-edd2c0f1e23a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 7, 1, 'None'),
('Kids Hot Chocolate', 'Small hot chocolate with marshmallows.', 4.00, 'https://images.unsplash.com/photo-1542990253-369dd4e91843?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 7, 1, 'Dairy'),
('Kids Pancakes', 'Mini pancakes with syrup.', 8.00, 'https://images.unsplash.com/photo-1528207776459-edd2c0f1e23a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 7, 1, 'Dairy,Gluten,Eggs'),
('Kids Smoothie', 'Strawberry banana smoothie.', 5.00, 'https://images.unsplash.com/photo-1505252585461-04db1eb84625?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 7, 1, 'None'),
('Breakfast Combo', 'Flat White + Smashed Avocado Toast. Save $3!', 20.50, 'https://images.unsplash.com/photo-1482049016688-2d3e1ac02b8f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 8, 1, 'Dairy,Gluten,Eggs'),
('Buy 1 Get 1 Half Price Coffee', 'Two coffees for the price of 1.5!', 7.50, 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 8, 1, 'Dairy'),
('Coffee & Pastry Deal', 'Any Coffee + Croissant. Save $2!', 8.00, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 8, 1, 'Dairy,Gluten'),
('Family Combo', '2 Adult Coffees + 2 Kids Items. Save $5!', 18.00, 'https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 8, 1, 'Dairy'),
('Lunch Special', 'Burger + Fries + Soft Drink. Save $4!', 22.00, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 8, 1, 'Gluten,Dairy'),
('Pastry Bundle', '3 Pastries of your choice. Save $2!', 11.00, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 8, 1, 'Dairy,Gluten');

--
-- Insert data for inventory (stock levels mapped to product_ids 1-60 in insertion order)
--

INSERT INTO `inventory` (`inventory_id`, `product_id`, `stock_level`) VALUES
('inv1', 1, 45),
('inv2', 2, 40),
('inv3', 3, 85),
('inv4', 4, 100),
('inv5', 5, 55),
('inv6', 6, 75),
('inv7', 7, 80),
('inv8', 8, 50),
('inv9', 9, 65),
('inv10', 10, 60),
('inv11', 11, 70),
('inv12', 12, 90),
('inv13', 13, 60),
('inv14', 14, 55),
('inv15', 15, 70),
('inv16', 16, 65),
('inv17', 17, 50),
('inv18', 18, 50),
('inv19', 19, 45),
('inv20', 20, 35),
('inv21', 21, 25),
('inv22', 22, 30),
('inv23', 23, 35),
('inv24', 24, 30),
('inv25', 25, 40),
('inv26', 26, 45),
('inv27', 27, 40),
('inv28', 28, 30),
('inv29', 29, 40),
('inv30', 30, 35),
('inv31', 31, 25),
('inv32', 32, 50),
('inv33', 33, 50),
('inv34', 34, 45),
('inv35', 35, 40),
('inv36', 36, 60),
('inv37', 37, 45),
('inv38', 38, 50),
('inv39', 39, 50),
('inv40', 40, 55),
('inv41', 41, 65),
('inv42', 42, 70),
('inv43', 43, 50),
('inv44', 44, 60),
('inv45', 45, 55),
('inv46', 46, 60),
('inv47', 47, 70),
('inv48', 48, 45),
('inv49', 49, 50),
('inv50', 50, 50),
('inv51', 51, 60),
('inv52', 52, 45),
('inv53', 53, 35),
('inv54', 54, 40),
('inv55', 55, 30),
('inv56', 56, 50),
('inv57', 57, 40),
('inv58', 58, 20),
('inv59', 59, 25),
('inv60', 60, 35);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;