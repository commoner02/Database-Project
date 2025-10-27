<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Appointment Management System</title>                                     
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-[#20B2AA] text-white shadow-sm">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-bold">Hospital Management System</h1>
                <nav class="flex space-x-4">
                    <a href="index.php" class="px-3 py-1 rounded <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-white text-[#20B2AA]' : 'hover:bg-white hover:text-[#20B2AA]' ?>">Dashboard</a>
                    <a href="queries.php" class="px-3 py-1 rounded <?= basename($_SERVER['PHP_SELF']) == 'queries.php' ? 'bg-white text-[#20B2AA]' : 'hover:bg-white hover:text-[#20B2AA]' ?>">SQL Queries</a>
                    <a href="analytics.php" class="px-3 py-1 rounded <?= basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'bg-white text-[#20B2AA]' : 'hover:bg-white hover:text-[#20B2AA]' ?>">Analytics</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-4">