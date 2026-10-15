<!--start header -->
<header>
    <div class="topbar d-flex align-items-center">
        <nav class="navbar navbar-expand gap-3">
            <div class="mobile-toggle-menu"><i class='bx bx-menu'></i></div>

            <div class="search-bar d-lg-block d-none" data-bs-toggle="modal" data-bs-target="#SearchModal">
            </div>

            <div class="top-menu ms-auto">
                <ul class="navbar-nav align-items-center gap-1">
                    <li class="nav-item dark-mode d-none d-sm-flex">
                        <a class="nav-link dark-mode-icon" href="javascript:;" role="button">
                            <i class='bx bx-moon'></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-sm-flex">
                        <a class="nav-link" href="{{ route('admin.dashboard') }}" role="button" title="Dashboard">
                            <i class='bx bx-home-circle'></i>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="user-box dropdown px-3">
                <a class="d-flex align-items-center nav-link dropdown-toggle gap-3 dropdown-toggle-nocaret"
                    href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="{{ auth()->user()?->avatarUrl() ?? asset('admin/images/avatars/avatar-2.png') }}" class="user-img" style="object-fit: cover;" alt="user avatar">
                    <div class="user-info">
                        <p class="user-name mb-0">{{ trim((auth()->user()?->first_name ?? '') . ' ' . (auth()->user()?->last_name ?? '')) ?: 'Admin' }}</p>
                        <p class="designattion mb-0">{{ auth()->user()?->email ?? 'Administrator' }}</p>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="{{ route('admin.dashboard') }}">
                            <i class="bx bx-tachometer fs-5 me-2"></i><span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="{{ route('admin.profile') }}">
                            <i class="bx bx-user-circle fs-5 me-2"></i><span>My Profile</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="{{ route('admin.profile', ['tab' => 'password']) }}">
                            <i class="bx bx-lock-alt fs-5 me-2"></i><span>Change Password</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item d-flex align-items-center">
                                <i class="bx bx-log-out-circle fs-5 me-2"></i><span>Logout</span>
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</header>
<!--end header -->
