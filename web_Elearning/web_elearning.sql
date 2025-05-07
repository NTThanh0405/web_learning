-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 16, 2025 at 10:14 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `web_elearning`
--

-- --------------------------------------------------------

--
-- Table structure for table `answers`
--

CREATE TABLE `answers` (
  `id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `answer` text NOT NULL,
  `status` enum('active','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `group_id`, `user_id`, `message`, `created_at`) VALUES
(2, 3, 2, 'hi', '2025-03-21 10:57:52'),
(3, 3, 2, 'vào nhóm', '2025-03-26 14:48:00'),
(4, 3, 3, 'hi', '2025-04-15 10:21:54');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `announcement` text DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category` varchar(50) NOT NULL DEFAULT 'cong_nghe_thong_tin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `announcement`, `teacher_id`, `thumbnail`, `status`, `created_at`, `updated_at`, `category`) VALUES
(17, 'học HTML', 'base', NULL, 2, 'assets/uploads/courses/course_1742812194.png', 'active', '2025-03-24 10:29:54', '2025-03-24 10:29:54', 'cong_nghe_thong_tin'),
(18, 'Lập trình vi xử lý', 'base', NULL, 2, 'assets/uploads/courses/course_1742812814.png', 'active', '2025-03-24 10:40:14', '2025-03-24 10:40:14', 'cong_nghe_thong_tin');

-- --------------------------------------------------------

--
-- Table structure for table `course_enrollments`
--

CREATE TABLE `course_enrollments` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `grade` float DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `course_enrollments`
--

INSERT INTO `course_enrollments` (`id`, `course_id`, `student_id`, `status`, `grade`, `completed_at`, `enrolled_at`) VALUES
(16, 18, 3, 'approved', NULL, NULL, '2025-03-25 09:36:27'),
(19, 17, 3, 'approved', NULL, NULL, '2025-03-25 10:04:14');

-- --------------------------------------------------------

--
-- Table structure for table `forum_comments`
--

CREATE TABLE `forum_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `status` enum('active','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `parent_comment_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `forum_comments`
--

INSERT INTO `forum_comments` (`id`, `post_id`, `user_id`, `comment`, `status`, `created_at`, `updated_at`, `parent_comment_id`) VALUES
(16, 33, 3, 'hi', 'active', '2025-04-11 11:19:36', '2025-04-11 11:19:36', NULL),
(18, 33, 2, '@ngô xuân mạnh hole', 'active', '2025-04-12 09:30:59', '2025-04-12 09:30:59', 16),
(19, 33, 3, '@ngô xuân mạnh helo', 'active', '2025-04-12 11:11:16', '2025-04-12 11:11:16', 16),
(20, 33, 2, '@ngô xuân mạnh hi', 'active', '2025-04-12 11:11:39', '2025-04-12 11:11:39', 16);

-- --------------------------------------------------------

--
-- Table structure for table `forum_posts`
--

CREATE TABLE `forum_posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('active','deleted') DEFAULT 'active',
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `forum_posts`
--

INSERT INTO `forum_posts` (`id`, `user_id`, `title`, `content`, `image_path`, `status`, `deleted_by`, `created_at`, `updated_at`) VALUES
(33, 3, 'hi mn', '12', NULL, 'active', NULL, '2025-04-11 10:07:48', '2025-04-11 10:07:48');

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `creator_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `thumbnail` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `groups`
--

INSERT INTO `groups` (`id`, `name`, `description`, `creator_id`, `created_at`, `updated_at`, `thumbnail`) VALUES
(3, 'backend', 'lập trình', 2, '2025-03-21 10:09:29', '2025-03-21 10:09:29', 'assets/uploads/groups/group_1742551769.png');

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`id`, `group_id`, `user_id`, `joined_at`) VALUES
(3, 3, 3, '2025-04-15 10:43:36');

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `course_id`, `title`, `description`, `video_path`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 17, 'Tổng quan về HTML', NULL, NULL, 2, '2025-04-08 13:09:14', '2025-04-08 13:09:14'),
(2, 17, 'Các câu lệnh HTML', NULL, NULL, 2, '2025-04-12 10:17:15', '2025-04-12 10:17:15');

-- --------------------------------------------------------

--
-- Table structure for table `lesson_items`
--

CREATE TABLE `lesson_items` (
  `id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `order_number` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `lesson_items`
--

INSERT INTO `lesson_items` (`id`, `lesson_id`, `title`, `description`, `order_number`, `created_at`, `updated_at`) VALUES
(1, 1, 'I. Tổng quan', NULL, 1, '2025-04-08 13:09:14', '2025-04-08 13:09:14'),
(2, 2, 'I. Các thẻ html', NULL, 1, '2025-04-12 10:17:15', '2025-04-12 10:17:15');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `type` enum('course','forum','group') NOT NULL,
  `scope` enum('global','course') DEFAULT 'global',
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `sender_id`, `type`, `scope`, `title`, `content`, `related_id`, `is_read`, `created_at`) VALUES
(1, 2, 3, 'forum', '', 'Bình luận mới', 'Có bình luận mới trong bài đăng của bạn.', 1, 1, '2025-04-10 09:54:27'),
(2, 3, 2, 'forum', '', 'Trả lời bình luận', 'Có người đã trả lời bình luận của bạn.', 1, 1, '2025-04-10 09:56:55'),
(3, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'học nhiều có lợi không\'.', 2, 1, '2025-04-10 09:58:59'),
(4, 3, 2, 'forum', '', 'Bình luận mới', 'Có bình luận mới trong bài đăng của bạn.', 2, 1, '2025-04-10 14:06:42'),
(5, 2, 2, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'liệu có qua môn\'.', 3, 1, '2025-04-11 02:37:40'),
(6, 2, 3, 'forum', '', 'Bình luận mới', 'Có bình luận mới trong bài đăng của bạn.', 3, 1, '2025-04-11 03:04:13'),
(7, 3, 2, '', '', 'Trả lời bình luận', 'Có người đã trả lời bình luận của bạn.', 3, 1, '2025-04-11 03:04:51'),
(8, 2, 3, 'forum', '', 'Bình luận mới', 'Có bình luận mới trong bài đăng của bạn.', 3, 1, '2025-04-11 03:07:06'),
(9, 2, 2, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'liệu có qua môn\'.', 4, 1, '2025-04-11 03:15:47'),
(10, 2, 3, 'forum', '', 'Bình luận mới', 'Có bình luận mới trong bài đăng của bạn.', 4, 1, '2025-04-11 03:23:40'),
(11, 2, 3, '', '', 'Trả lời bình luận', 'Có người đã trả lời bình luận của bạn: \'@thanh\'', 4, 1, '2025-04-11 03:23:40'),
(12, 2, 3, 'forum', '', 'Bình luận mới', 'Có bình luận mới trong bài đăng của bạn.', 4, 1, '2025-04-11 03:23:53'),
(13, 2, 3, 'forum', '', 'Bình luận mới', 'Có bình luận mới trong bài đăng của bạn.', 4, 1, '2025-04-11 03:25:14'),
(14, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'cố gắng\'.', 5, 1, '2025-04-11 05:45:13'),
(15, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'cố gắng\'.', 6, 1, '2025-04-11 05:49:28'),
(16, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'cố gắng\'.', 7, 1, '2025-04-11 06:10:24'),
(17, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'học hành gì chưa người đẹp\'.', 8, 1, '2025-04-11 06:23:05'),
(18, 2, 2, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'hi mn\'.', 9, 1, '2025-04-11 07:05:37'),
(19, 2, 3, 'forum', '', 'Bình luận mới', 'Có bình luận mới trong bài đăng của bạn.', 9, 1, '2025-04-11 07:06:17'),
(20, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'oh ye\'.', 10, 1, '2025-04-11 07:06:35'),
(21, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'comment\'.', 11, 1, '2025-04-11 07:06:58'),
(22, 2, 2, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'lú\'.', 12, 1, '2025-04-11 07:07:18'),
(23, 2, 2, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'hi mn\'.', 13, 1, '2025-04-11 07:08:01'),
(24, 2, 2, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'ok\'.', 14, 1, '2025-04-11 07:08:08'),
(25, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'ok\'.', 15, 1, '2025-04-11 07:22:21'),
(26, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'1\'.', 16, 1, '2025-04-11 07:38:07'),
(27, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'2\'.', 17, 1, '2025-04-11 07:38:16'),
(28, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'1\'.', 18, 1, '2025-04-11 07:47:04'),
(29, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'3\'.', 19, 1, '2025-04-11 07:47:17'),
(30, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'5\'.', 20, 1, '2025-04-11 07:47:28'),
(31, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'hi\'.', 21, 1, '2025-04-11 07:48:25'),
(32, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'4\'.', 22, 1, '2025-04-11 07:49:16'),
(33, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'1\'.', 23, 1, '2025-04-11 07:58:07'),
(34, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'3\'.', 24, 1, '2025-04-11 07:58:14'),
(35, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'3\'.', 25, 1, '2025-04-11 08:02:25'),
(36, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'1\'.', 26, 1, '2025-04-11 08:10:26'),
(37, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'4\'.', 27, 1, '2025-04-11 08:10:34'),
(38, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'1\'.', 28, 1, '2025-04-11 08:11:37'),
(39, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'3\'.', 29, 1, '2025-04-11 08:11:50'),
(40, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'3\'.', 30, 1, '2025-04-11 08:13:03'),
(41, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'2\'.', 31, 1, '2025-04-11 08:13:37'),
(42, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'2\'.', 32, 1, '2025-04-11 08:13:47'),
(43, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'học html\'.', 36, 1, '2025-04-11 10:39:35'),
(44, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'học html\'.', 37, 1, '2025-04-11 10:48:00'),
(45, 2, 3, 'forum', '', 'Bài đăng mới trên diễn đàn', 'Một bài đăng mới đã được tạo: \'học html\'.', 39, 1, '2025-04-11 10:49:54');

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `lesson_item_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `page_number` int(11) NOT NULL DEFAULT 1,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `lesson_item_id`, `title`, `content`, `page_number`, `file_path`, `created_at`, `updated_at`) VALUES
(1, 1, '1.1 HTML là gì', 'HTML = HyperText Markup Language\r\nHypertext refers to text that contains links to other documents or resources. In HTML, hypertext allows users to navigate between different web pages by clicking on links.\r\nMarkup refers to the annotations or tags that are used to define the structure and formatting of content in a document. In HTML, markup tags are used to specify elements such as headings, paragraphs, images, and links that make a web page visually appealing and easy to navigate. These tags provide information to web browsers about how the content should be structured, styled, and rendered on the user\'s screen.\r\nLanguage refers to the system of rules and syntax used to write and interpret the markup in a consistent and standardized manner. HTML provides a set of predefined tags and attributes that define how content should be displayed and interacted with in a web browser. It\'s the universal translator, the language that humans, computers and browsers understand. ||SPLIT|| HTML\'s Best Friends: CSS and JavaScript\r\nWith HTML you have the power to organize and structure your content in a way that makes sense. It\'s like creating a blueprint for your web page, where you can define headings, paragraphs, images, links, and so much more. Think of it as your elementary tool for crafting engaging and interactive online experiences.\r\n\r\nAs you will notice soon, HTML is not alone in this adventure. It teams up with its partners in crime, CSS (Cascading Style Sheets) and JavaScript. Think of CSS as the paint and decorations that make a room look good, giving websites their style and flair. JavaScript is like the gadgets in a room, adding fun and interactive features that make the website more interesting and useful.', 1, NULL, '2025-04-08 13:09:14', '2025-04-08 13:09:14'),
(2, 1, 'The Birth of HTML', 'It all started back in 1989 when Sir Tim Berners-Lee invented HTML while working at CERN, the European Organization for Nuclear Research. His vision was to create a way to share scientific information easily across different computers.\r\n\r\nA drawing of Sir Tim Berners-Lee\r\nThe first web page, created by Sir Tim Berners-Lee, was a simple yet groundbreaking creation. It consisted of plain text and hyperlinks that allowed users to navigate and explore different documents and resources. This humble page laid the foundation for a revolutionary era of information sharing and transformed the way we access and interact with content on the internet.\r\n\r\nThe first version of HTML, called HTML 1.0, was released in 1993. It was a simple markup language with limited features compared to the modern versions we use today.\r\n\r\nOver the years, HTML has evolved and new versions have been introduced, each bringing new elements, attributes, and features to enhance the capabilities of web development. HTML 5, the current version, was released in 2014 and introduced many exciting features such as native multimedia support, semantic elements, and improved form controls. So, next time you\'re building a web page, remember the journey that HTML has taken to become the versatile and powerful language it is today!', 2, NULL, '2025-04-08 13:09:14', '2025-04-08 13:09:14'),
(3, 1, 'HTML Evolution', 'Introduction of HTML1 (1993)\r\nThe first version of HTML that laid the foundation for web development. It introduced basic elements like headings, paragraphs, and lists, paving the way for the future of HTML. Introduction of HTML2 (1995)\r\nWith HTML2, tables and the ability to embed images were introduced. This made it easier to create structured layouts and include visuals in web pages.', 3, NULL, '2025-04-08 13:09:14', '2025-04-08 13:09:14'),
(4, 2, 'Your First HTML File', 'Great! You\'re all set to create your first HTML file, and view your first web page. In order to get started, make sure you have a text editor and a web browser installed on your computer. These tools will be your best friends throughout this journey. Once you have them ready, follow these three steps:\r\n\r\nStep 1 – Create an HTML file\r\nOpen your text editor and create a new file. It\'s time to give life to your web page! Save the file with a memorable name and make sure to use the .html extension, such as sample.html. This tells the computer that it\'s an HTML file.\r\n||SPLIT||\r\n\r\nStep 2 – Write the HTML code\r\nHere comes the exciting part! Inside your HTML file, you\'ll start by adding the essential structure of an HTML document. We\'ve got you covered with the code you need. Just copy the following:\r\n\r\nindex.html\r\n<!DOCTYPE html>\r\n<html>\r\n<head>\r\n  <title>My First Web Page</title>\r\n</head>\r\n<body>\r\n  <h1>Hello, World!</h1>\r\n  <p>This is my first web page.</p>\r\n</body>\r\n</html>\r\n\r\n\r\nLook at that! You\'ve laid the foundation for your web page. The code sets up the structure, adds a title, and includes a heading and a paragraph. Feel free to customize the content to express your creativity and personality.\r\n\r\nDon\'t worry if you\'re not familiar with all the concepts just yet. We\'ll cover everything in detail later in the course, so you\'ll have a solid understanding. For now, let\'s focus on getting started with creating your HTML file and opening it in a web browser. Exciting times ahead!', 1, NULL, '2025-04-12 10:17:15', '2025-04-12 10:17:15'),
(5, 2, 'Your First HTML File', 'Step 3 – Save and open the HTML file\r\nNow it\'s time to see your creation come to life! Save the HTML file and navigate to the location where you saved it. Double-click on the file, and like magic, it will open in your default web browser. Voila! You\'ll see your web page displayed beautifully.\r\n\r\nWow, you did it! You\'ve successfully created and opened your first HTML file. Take a moment to appreciate your accomplishment and the incredible possibilities that lie ahead in your web development journey.\r\n\r\nRemember, this is just the beginning. The more you practice and explore, the more you\'ll learn and discover. We hope you’ll soon have fun experimenting with different HTML elements, adding images, and playing with styles to make your web page uniquely yours. ||SPLIT||\r\nEdube™ Sandbox\r\nGreat news! In this course, you\'ll get access to the Edube Sandbox tool. It lets you write, test, and preview your HTML code right away. No need to install or configure any software on your computer.\r\n\r\nTo access Edube Sanbox, simply find and click the Editor button on your screen, or click the \"Run\" button next to the HTML code we provide in the course, like this:\r\n\r\nindex.html\r\nRun\r\n<!DOCTYPE html>\r\n<html>\r\n<head>\r\n  <title>My First Web Page</title>\r\n</head>\r\n<body>\r\n  <h1>Hello, World!</h1>\r\n  <p>This is my first web page.</p>\r\n</body>\r\n</html>\r\n\r\n\r\nOnce there, you can edit your code in the editor. With a click of the \"Run\" button, you\'ll see your code come to life. We hope you enjoy it!', 2, NULL, '2025-04-12 10:31:29', '2025-04-12 10:31:29');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tests`
--

CREATE TABLE `tests` (
  `id` int(11) NOT NULL,
  `type` enum('lesson','course') NOT NULL,
  `course_id` int(11) NOT NULL,
  `lesson_id` int(11) DEFAULT NULL,
  `question` text NOT NULL,
  `option1` varchar(255) NOT NULL,
  `option2` varchar(255) NOT NULL,
  `option3` varchar(255) NOT NULL,
  `option4` varchar(255) NOT NULL,
  `correct_option` int(11) NOT NULL,
  `max_score` float NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `tests`
--

INSERT INTO `tests` (`id`, `type`, `course_id`, `lesson_id`, `question`, `option1`, `option2`, `option3`, `option4`, `correct_option`, `max_score`, `created_at`) VALUES
(1, 'lesson', 17, 1, 'câu 1: ngày 30-4 là ngày gì', 'a. quốc tế thiếu nhi', 'b. ngày khát nước', 'c. ngày hạnh phúc', 'd. ngày giải phóng miền nam', 4, 1, '2025-04-12 10:04:54'),
(2, 'lesson', 17, 1, 'câu 2: nói thật có qua môn không :))', 'a. có', 'b. không', 'c. hên xui', 'd. hỏi cô', 1, 1, '2025-04-12 10:04:54'),
(3, 'lesson', 17, 1, 'câu 3: database có khó không', 'a. thường thôi', 'b. khó', 'c. dễ ợt', 'd. đoán xem', 2, 1, '2025-04-12 10:04:54'),
(4, 'course', 17, NULL, 'học xong chưa', 'rồi', 'chưa', 'đố biết', 'học được tí rồi', 4, 1, '2025-04-15 02:22:13'),
(5, 'lesson', 17, 1, 'hoàng sa trường sa là của việt nam', 'chuẩn cmnr', 'tôi là cali nên không biết', 'của tung của', 'tôi bị nguu', 1, 1, '2025-04-15 03:00:57');

-- --------------------------------------------------------

--
-- Table structure for table `test_images`
--

CREATE TABLE `test_images` (
  `id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `type` enum('question','option') NOT NULL,
  `option_index` int(11) DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_results`
--

CREATE TABLE `test_results` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `score` float NOT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `completed_at` timestamp NULL DEFAULT NULL,
  `course_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `test_results`
--

INSERT INTO `test_results` (`id`, `student_id`, `test_id`, `score`, `attempt_number`, `completed_at`, `course_id`) VALUES
(1, 3, 3, 0, 1, '2025-04-16 07:19:05', 17),
(4, 3, 2, 0, 1, '2025-04-16 07:19:05', 17),
(5, 3, 1, 0, 1, '2025-04-16 07:19:05', 17),
(29, 3, 5, 0, 1, '2025-04-16 07:19:05', 17);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `last_active` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `role`, `avatar`, `remember_token`, `last_active`, `created_at`, `updated_at`, `profile_picture`) VALUES
(1, 'admin', '$2y$10$adxQiA8kkAGeTVWJdB.AEOqjT/Ld7QyDLizTiiAELJYfeOXkQQnCO', 'admin@gmail.com', 'Administrator', 'admin', NULL, NULL, NULL, '2025-03-18 03:38:06', '2025-03-18 03:54:14', NULL),
(2, '', '$2y$10$U4UuVsSNrJl5nsKNaFgXZ.xtsJVWwwDE4pHhN2ifR9PGpwweSvygW', 'thanh@gmail.com', 'thanh', 'teacher', 'assets/uploads/profile_pictures/avatar_2_1742455968.jpg', NULL, NULL, '2025-03-18 07:05:24', '2025-03-20 07:32:48', NULL),
(3, 'manh', '$2y$10$rjwX/xWknd8/DfeJzEJW/eD8hBbCuTcx4TPaGY../ymTz9TwW.IjC', 'manh@gmail.com', 'ngô xuân mạnh', 'student', 'assets/uploads/profile_pictures/avatar_3_1744709422.jpg', NULL, NULL, '2025-03-18 08:20:54', '2025-04-15 09:30:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_page_progress`
--

CREATE TABLE `user_page_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `user_page_progress`
--

INSERT INTO `user_page_progress` (`id`, `user_id`, `page_id`, `completed`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 1, '2025-04-16 07:11:10', '2025-04-08 13:11:12', '2025-04-16 07:11:10'),
(5, 3, 2, 1, '2025-04-16 07:11:12', '2025-04-08 15:03:08', '2025-04-16 07:11:12'),
(19, 3, 3, 1, '2025-04-16 07:11:13', '2025-04-09 14:00:17', '2025-04-16 07:11:13'),
(40, 3, 4, 1, '2025-04-15 02:46:13', '2025-04-12 10:39:24', '2025-04-15 02:46:13'),
(134, 3, 5, 1, '2025-04-15 02:46:15', '2025-04-15 02:46:15', '2025-04-15 02:46:15');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comment_id` (`comment_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_student_unique` (`course_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `forum_comments`
--
ALTER TABLE `forum_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_comment_id` (`parent_comment_id`);

--
-- Indexes for table `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `deleted_by` (`deleted_by`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `creator_id` (`creator_id`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_user_unique` (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `lessons_ibfk_2` (`course_id`);

--
-- Indexes for table `lesson_items`
--
ALTER TABLE `lesson_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_id` (`lesson_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_item_id` (`lesson_item_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`);

--
-- Indexes for table `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `lesson_id` (`lesson_id`);

--
-- Indexes for table `test_images`
--
ALTER TABLE `test_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`);

--
-- Indexes for table `test_results`
--
ALTER TABLE `test_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_test_attempt` (`student_id`,`test_id`,`attempt_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `completed_at` (`completed_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_page_progress`
--
ALTER TABLE `user_page_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_page_unique` (`user_id`,`page_id`),
  ADD KEY `page_id` (`page_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `answers`
--
ALTER TABLE `answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `forum_comments`
--
ALTER TABLE `forum_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `forum_posts`
--
ALTER TABLE `forum_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lesson_items`
--
ALTER TABLE `lesson_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tests`
--
ALTER TABLE `tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `test_images`
--
ALTER TABLE `test_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `test_results`
--
ALTER TABLE `test_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_page_progress`
--
ALTER TABLE `user_page_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=174;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `answers`
--
ALTER TABLE `answers`
  ADD CONSTRAINT `answers_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `forum_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `answers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_enrollments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `forum_comments`
--
ALTER TABLE `forum_comments`
  ADD CONSTRAINT `forum_comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_comments_ibfk_3` FOREIGN KEY (`parent_comment_id`) REFERENCES `forum_comments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD CONSTRAINT `forum_posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_posts_ibfk_2` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lessons_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lesson_items`
--
ALTER TABLE `lesson_items`
  ADD CONSTRAINT `lesson_items_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pages`
--
ALTER TABLE `pages`
  ADD CONSTRAINT `pages_ibfk_1` FOREIGN KEY (`lesson_item_id`) REFERENCES `lesson_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`email`) REFERENCES `users` (`email`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tests`
--
ALTER TABLE `tests`
  ADD CONSTRAINT `tests_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tests_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `test_images`
--
ALTER TABLE `test_images`
  ADD CONSTRAINT `test_images_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_results`
--
ALTER TABLE `test_results`
  ADD CONSTRAINT `test_results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_results_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_results_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_page_progress`
--
ALTER TABLE `user_page_progress`
  ADD CONSTRAINT `user_page_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_page_progress_ibfk_2` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
