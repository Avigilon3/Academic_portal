<head>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
</head>


<aside class="sidebar" id="admin-sidebar" data-sidebar>
  <nav class="sidebar-nav" aria-label="Main navigation">
    <a href="./dashboard.php" class="nav-item" aria-label="Dashboard" title="Dashboard">
      <span class="material-symbols-rounded">dashboard</span>
      <span class="nav-label">Dashboard</span>
    </a>


<!-- Dropdown -->
  <div class="dropdown" data-dropdown data-dropdown-key="database">
    <a href="./database_management.php" class="nav-item dropdown-toggle" aria-label="Database Management" title="Database Management" aria-expanded="false"> 
      <span class="material-symbols-rounded">database</span>
      <span class="nav-label">Database Management</span>
      <span class="material-symbols-rounded dropdown-arrow">keyboard_arrow_down</span>
    </a>


    <!-- Dropdown menu -->
    <ul class="submenu">
        <li><a class="nav-item" href="./students.php">Students</a></li>
        <li><a class="nav-item" href="./professors.php">Professors</a></li>
        <li><a class="nav-item" href="./subjects.php">Courses / Subjects</a></li>
        <li><a class="nav-item" href="./sections.php">Sections</a></li>
    </ul>
  </div>


    <a href="./masterlist.php" class="nav-item" aria-label="Masterlist" title="Masterlist">
      <span class="material-symbols-rounded">list_alt</span>
      <span class="nav-label">Masterlist</span>
    </a>
    <a href="./grading_management.php" class="nav-item" aria-label="Grading Sheets" title="Grading Sheets">
      <span class="material-symbols-rounded">stacks</span>
      <span class="nav-label">Grading Sheets</span>
    </a>
    <a href="./report_management.php" class="nav-item" aria-label="Requests" title="Requests">
      <span class="material-symbols-rounded">error</span>
      <span class="nav-label">Requests</span>
    </a>
    <a href="./reports.php" class="nav-item" aria-label="Reports" title="Reports">
      <span class="material-symbols-rounded">docs</span>
      <span class="nav-label">Reports</span>
    </a>
    <a href="./activity_logs.php" class="nav-item" aria-label="Activity Logs" title="Activity Logs">
      <span class="material-symbols-rounded">earthquake</span>
      <span class="nav-label">Activity Logs</span>
    </a>
  </nav>
</aside>
