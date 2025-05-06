CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sitin_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `lab_pcs` (
  `pc_id` int(11) NOT NULL,
  `lab_number` int(11) NOT NULL,
  `pc_number` int(11) NOT NULL,
  `status` enum('available','unavailable') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `lab_pcs` (`pc_id`, `lab_number`, `pc_number`, `status`) VALUES
(1, 524, 1, 'unavailable'),
(2, 524, 2, 'unavailable'),
(3, 524, 3, 'unavailable'),
(10, 524, 4, 'unavailable'),
(11, 524, 5, 'unavailable'),
(12, 524, 6, 'unavailable'),
(13, 524, 7, 'unavailable'),
(14, 524, 8, 'unavailable'),
(15, 524, 9, 'unavailable'),
(16, 524, 10, 'unavailable'),
(17, 524, 11, 'unavailable'),
(18, 524, 12, 'unavailable'),
(19, 524, 13, 'unavailable'),
(20, 524, 14, 'unavailable'),
(21, 524, 15, 'unavailable'),
(22, 524, 16, 'unavailable'),
(23, 524, 17, 'unavailable'),
(24, 524, 18, 'unavailable'),
(25, 524, 19, 'unavailable'),
(26, 524, 20, 'unavailable'),
(27, 524, 21, 'unavailable'),
(28, 524, 22, 'unavailable'),
(29, 524, 23, 'unavailable'),
(30, 524, 24, 'unavailable'),
(31, 524, 25, 'unavailable'),
(32, 524, 26, 'unavailable'),
(33, 524, 27, 'unavailable'),
(34, 524, 28, 'unavailable'),
(35, 524, 29, 'unavailable'),
(36, 524, 30, 'unavailable'),
(37, 524, 31, 'unavailable'),
(38, 524, 32, 'unavailable'),
(39, 524, 33, 'unavailable'),
(40, 524, 34, 'unavailable'),
(41, 524, 35, 'unavailable'),
(42, 524, 36, 'unavailable'),
(43, 524, 37, 'unavailable'),
(44, 524, 38, 'unavailable'),
(45, 524, 39, 'unavailable'),
(46, 524, 40, 'unavailable'),
(47, 524, 41, 'unavailable'),
(48, 524, 42, 'unavailable'),
(49, 524, 43, 'unavailable'),
(50, 524, 44, 'unavailable'),
(51, 524, 45, 'unavailable'),
(52, 524, 46, 'unavailable'),
(53, 524, 47, 'unavailable'),
(54, 524, 48, 'unavailable'),
(55, 524, 49, 'unavailable'),
(56, 524, 50, 'unavailable'),
(57, 526, 1, 'available'),
(58, 526, 2, 'available'),
(59, 526, 3, 'available'),
(60, 526, 4, 'available'),
(61, 526, 5, 'available'),
(62, 526, 6, 'available'),
(63, 526, 7, 'available'),
(64, 526, 8, 'available'),
(65, 526, 9, 'available'),
(66, 526, 10, 'available'),
(67, 526, 11, 'available'),
(68, 526, 12, 'available'),
(69, 526, 13, 'available'),
(70, 526, 14, 'available'),
(71, 526, 15, 'available'),
(72, 526, 16, 'available'),
(73, 526, 17, 'available'),
(74, 526, 18, 'available'),
(75, 526, 19, 'available'),
(76, 526, 20, 'available'),
(77, 526, 21, 'available'),
(78, 526, 22, 'available'),
(79, 526, 23, 'available'),
(80, 526, 24, 'available'),
(81, 526, 25, 'available'),
(82, 526, 26, 'available'),
(83, 526, 27, 'available'),
(84, 526, 28, 'available'),
(85, 526, 29, 'available'),
(86, 526, 30, 'available'),
(87, 526, 31, 'available'),
(88, 526, 32, 'available'),
(89, 526, 33, 'available'),
(90, 526, 34, 'available'),
(91, 526, 35, 'available'),
(92, 526, 36, 'available'),
(93, 526, 37, 'available'),
(94, 526, 38, 'available'),
(95, 526, 39, 'available'),
(96, 526, 40, 'available'),
(97, 526, 41, 'available'),
(98, 526, 42, 'available'),
(99, 526, 43, 'available'),
(100, 526, 44, 'available'),
(101, 526, 45, 'available'),
(102, 526, 46, 'available'),
(103, 526, 47, 'available'),
(104, 526, 48, 'available'),
(105, 526, 49, 'available'),
(106, 526, 50, 'available'),
(107, 528, 1, 'available'),
(108, 528, 2, 'available'),
(109, 528, 3, 'available'),
(110, 528, 4, 'available'),
(111, 528, 5, 'available'),
(112, 528, 6, 'available'),
(113, 528, 7, 'available'),
(114, 528, 8, 'available'),
(115, 528, 9, 'available'),
(116, 528, 10, 'available'),
(117, 528, 11, 'available'),
(118, 528, 12, 'available'),
(119, 528, 13, 'available'),
(120, 528, 14, 'available'),
(121, 528, 15, 'available'),
(122, 528, 16, 'available'),
(123, 528, 17, 'available'),
(124, 528, 18, 'available'),
(125, 528, 19, 'available'),
(126, 528, 20, 'available'),
(127, 528, 21, 'available'),
(128, 528, 22, 'available'),
(129, 528, 23, 'available'),
(130, 528, 24, 'available'),
(131, 528, 25, 'available'),
(132, 528, 26, 'available'),
(133, 528, 27, 'available'),
(134, 528, 28, 'available'),
(135, 528, 29, 'available'),
(136, 528, 30, 'available'),
(137, 528, 31, 'available'),
(138, 528, 32, 'available'),
(139, 528, 33, 'available'),
(140, 528, 34, 'available'),
(141, 528, 35, 'available'),
(142, 528, 36, 'available'),
(143, 528, 37, 'available'),
(144, 528, 38, 'available'),
(145, 528, 39, 'available'),
(146, 528, 40, 'available'),
(147, 528, 41, 'available'),
(148, 528, 42, 'available'),
(149, 528, 43, 'available'),
(150, 528, 44, 'available'),
(151, 528, 45, 'available'),
(152, 528, 46, 'available'),
(153, 528, 47, 'available'),
(154, 528, 48, 'available'),
(155, 528, 49, 'available'),
(156, 528, 50, 'available'),
(157, 530, 1, 'available'),
(158, 530, 2, 'available'),
(159, 530, 3, 'available'),
(160, 530, 4, 'available'),
(161, 530, 5, 'available'),
(162, 530, 6, 'available'),
(163, 530, 7, 'available'),
(164, 530, 8, 'available'),
(165, 530, 9, 'available'),
(166, 530, 10, 'available'),
(167, 530, 11, 'available'),
(168, 530, 12, 'available'),
(169, 530, 13, 'available'),
(170, 530, 14, 'available'),
(171, 530, 15, 'available'),
(172, 530, 16, 'available'),
(173, 530, 17, 'available'),
(174, 530, 18, 'available'),
(175, 530, 19, 'available'),
(176, 530, 20, 'available'),
(177, 530, 21, 'available'),
(178, 530, 22, 'available'),
(179, 530, 23, 'available'),
(180, 530, 24, 'available'),
(181, 530, 25, 'available'),
(182, 530, 26, 'available'),
(183, 530, 27, 'available'),
(184, 530, 28, 'available'),
(185, 530, 29, 'available'),
(186, 530, 30, 'available'),
(187, 530, 31, 'available'),
(188, 530, 32, 'available'),
(189, 530, 33, 'available'),
(190, 530, 34, 'available'),
(191, 530, 35, 'available'),
(192, 530, 36, 'available'),
(193, 530, 37, 'available'),
(194, 530, 38, 'available'),
(195, 530, 39, 'available'),
(196, 530, 40, 'available'),
(197, 530, 41, 'available'),
(198, 530, 42, 'available'),
(199, 530, 43, 'available'),
(200, 530, 44, 'available'),
(201, 530, 45, 'available'),
(202, 530, 46, 'available'),
(203, 530, 47, 'available'),
(204, 530, 48, 'available'),
(205, 530, 49, 'available'),
(206, 530, 50, 'available'),
(207, 542, 1, 'available'),
(208, 542, 2, 'available'),
(209, 542, 3, 'available'),
(210, 542, 4, 'available'),
(211, 542, 5, 'available'),
(212, 542, 6, 'available'),
(213, 542, 7, 'available'),
(214, 542, 8, 'available'),
(215, 542, 9, 'available'),
(216, 542, 10, 'available'),
(217, 542, 11, 'available'),
(218, 542, 12, 'available'),
(219, 542, 13, 'available'),
(220, 542, 14, 'available'),
(221, 542, 15, 'available'),
(222, 542, 16, 'available'),
(223, 542, 17, 'available'),
(224, 542, 18, 'available'),
(225, 542, 19, 'available'),
(226, 542, 20, 'available'),
(227, 542, 21, 'available'),
(228, 542, 22, 'available'),
(229, 542, 23, 'available'),
(230, 542, 24, 'available'),
(231, 542, 25, 'available'),
(232, 542, 26, 'available'),
(233, 542, 27, 'available'),
(234, 542, 28, 'available'),
(235, 542, 29, 'available'),
(236, 542, 30, 'available'),
(237, 542, 31, 'available'),
(238, 542, 32, 'available'),
(239, 542, 33, 'available'),
(240, 542, 34, 'available'),
(241, 542, 35, 'available'),
(242, 542, 36, 'available'),
(243, 542, 37, 'available'),
(244, 542, 38, 'available'),
(245, 542, 39, 'available'),
(246, 542, 40, 'available'),
(247, 542, 41, 'available'),
(248, 542, 42, 'available'),
(249, 542, 43, 'available'),
(250, 542, 44, 'available'),
(251, 542, 45, 'available'),
(252, 542, 46, 'available'),
(253, 542, 47, 'available'),
(254, 542, 48, 'available'),
(255, 542, 49, 'available'),
(256, 542, 50, 'available'),
(257, 544, 1, 'available'),
(258, 544, 2, 'available'),
(259, 544, 3, 'available'),
(260, 544, 4, 'available'),
(261, 544, 5, 'available'),
(262, 544, 6, 'available'),
(263, 544, 7, 'available'),
(264, 544, 8, 'available'),
(265, 544, 9, 'available'),
(266, 544, 10, 'available'),
(267, 544, 11, 'available'),
(268, 544, 12, 'available'),
(269, 544, 13, 'available'),
(270, 544, 14, 'available'),
(271, 544, 15, 'available'),
(272, 544, 16, 'available'),
(273, 544, 17, 'available'),
(274, 544, 18, 'available'),
(275, 544, 19, 'available'),
(276, 544, 20, 'available'),
(277, 544, 21, 'available'),
(278, 544, 22, 'available'),
(279, 544, 23, 'available'),
(280, 544, 24, 'available'),
(281, 544, 25, 'available'),
(282, 544, 26, 'available'),
(283, 544, 27, 'available'),
(284, 544, 28, 'available'),
(285, 544, 29, 'available'),
(286, 544, 30, 'available'),
(287, 544, 31, 'available'),
(288, 544, 32, 'available'),
(289, 544, 33, 'available'),
(290, 544, 34, 'available'),
(291, 544, 35, 'available'),
(292, 544, 36, 'available'),
(293, 544, 37, 'available'),
(294, 544, 38, 'available'),
(295, 544, 39, 'available'),
(296, 544, 40, 'available'),
(297, 544, 41, 'available'),
(298, 544, 42, 'available'),
(299, 544, 43, 'available'),
(300, 544, 44, 'available'),
(301, 544, 45, 'available'),
(302, 544, 46, 'available'),
(303, 544, 47, 'available'),
(304, 544, 48, 'available'),
(305, 544, 49, 'available'),
(306, 544, 50, 'available');


CREATE TABLE `lab_schedules` (
  `schedule_id` int(11) NOT NULL,
  `lab_number` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('available','unavailable') NOT NULL DEFAULT 'available',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `notification_type` enum('student','admin') NOT NULL DEFAULT 'student'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL,
  `idno` int(11) NOT NULL,
  `lab_number` int(11) NOT NULL,
  `pc_number` int(11) DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `time_in` time NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `status` enum('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `time_in_status` enum('pending','sit-inned','completed') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DELIMITER $$
CREATE TRIGGER `after_reservation_completed` AFTER UPDATE ON `reservations` FOR EACH ROW BEGIN
    IF NEW.time_in_status = 'completed' AND OLD.time_in_status != 'completed' THEN
        UPDATE lab_pcs 
        SET status = 'available' 
        WHERE lab_number = NEW.lab_number 
        AND pc_number = NEW.pc_number;
    END IF;
END
$$
DELIMITER ;

CREATE TABLE `resources` (
  `resource_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `is_folder` tinyint(1) DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL COMMENT 'For subfolders',
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rewards` (
  `reward_id` int(11) NOT NULL,
  `idno` int(11) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `points` int(11) DEFAULT 1,
  `sitin_id` int(11) NOT NULL,
  `rewarded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `sitin` (
  `sitin_id` int(11) NOT NULL,
  `idno` int(11) NOT NULL,
  `lab_number` int(11) NOT NULL,
  `sitin_date` date NOT NULL,
  `time_in` time NOT NULL,
  `time_out` time DEFAULT NULL,
  `purpose` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `idno` int(11) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `middlename` varchar(50) NOT NULL,
  `course` enum('BSIT','BSCS','HM','CRIM','CBA') NOT NULL,
  `level` enum('1','2','3','4') NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_picture` varchar(255) DEFAULT 'default-profile.png',
  `role` enum('student','admin','staff') NOT NULL DEFAULT 'student',
  `session` int(11) DEFAULT 30 CHECK (`role` = 'student' or `session` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `admin_id` (`admin_id`);

ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `fk_feedback_user` (`user_id`),
  ADD KEY `fk_feedback_sitin` (`sitin_id`);

ALTER TABLE `lab_pcs`
  ADD PRIMARY KEY (`pc_id`),
  ADD UNIQUE KEY `lab_number` (`lab_number`,`pc_number`);

ALTER TABLE `lab_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `lab_number` (`lab_number`,`day_of_week`,`start_time`,`end_time`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notifications_user` (`user_id`);

ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `idno` (`idno`),
  ADD KEY `fk_reservations_lab` (`lab_number`);

ALTER TABLE `resources`
  ADD PRIMARY KEY (`resource_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `parent_id` (`parent_id`);

ALTER TABLE `rewards`
  ADD PRIMARY KEY (`reward_id`),
  ADD KEY `idno` (`idno`),
  ADD KEY `sitin_id` (`sitin_id`),
  ADD KEY `rewarded_by` (`rewarded_by`);

ALTER TABLE `sitin`
  ADD PRIMARY KEY (`sitin_id`),
  ADD KEY `idno` (`idno`),
  ADD KEY `fk_sitin_lab` (`lab_number`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `idno` (`idno`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `lab_pcs`
  MODIFY `pc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=362;

ALTER TABLE `lab_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

ALTER TABLE `reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

ALTER TABLE `resources`
  MODIFY `resource_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `rewards`
  MODIFY `reward_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `sitin`
  MODIFY `sitin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_sitin` FOREIGN KEY (`sitin_id`) REFERENCES `sitin` (`sitin_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `lab_schedules`
  ADD CONSTRAINT `fk_schedules_lab` FOREIGN KEY (`lab_number`) REFERENCES `lab_pcs` (`lab_number`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_reservations_lab` FOREIGN KEY (`lab_number`) REFERENCES `lab_pcs` (`lab_number`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`idno`) REFERENCES `users` (`idno`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `resources`
  ADD CONSTRAINT `resources_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `resources_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `resources` (`resource_id`) ON DELETE CASCADE;

ALTER TABLE `rewards`
  ADD CONSTRAINT `rewards_ibfk_1` FOREIGN KEY (`idno`) REFERENCES `users` (`idno`) ON DELETE CASCADE,
  ADD CONSTRAINT `rewards_ibfk_2` FOREIGN KEY (`sitin_id`) REFERENCES `sitin` (`sitin_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rewards_ibfk_3` FOREIGN KEY (`rewarded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `sitin`
  ADD CONSTRAINT `fk_sitin_lab` FOREIGN KEY (`lab_number`) REFERENCES `lab_pcs` (`lab_number`) ON DELETE CASCADE,
  ADD CONSTRAINT `sitin_ibfk_1` FOREIGN KEY (`idno`) REFERENCES `users` (`idno`) ON DELETE CASCADE;
COMMIT;

-- Create comments table
CREATE TABLE IF NOT EXISTS comments (
    comment_id INT PRIMARY KEY AUTO_INCREMENT,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
); 