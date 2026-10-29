<!--sidebar wrapper -->
<div class="sidebar-wrapper" data-simplebar="true">
    <div class="sidebar-header">
        <div>
            <img src="{{ asset('admin/images/favicon-32x32.png') }}" class="logo-icon" alt="logo icon">
        </div>
        <div>
            <h4 class="logo-text">Part Synch</h4>
        </div>
        <div class="toggle-icon ms-auto"><i class='bx bx-arrow-back'></i></div>
    </div>
    <!--navigation-->
    <ul class="metismenu" id="menu">

        <li>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-home-circle'></i></div>
                <div class="menu-title">Dashboard</div>
            </a>
        </li>

        <li>
            <a href="{{ route('vendor.records') }}" class="{{ request()->routeIs('vendor.records') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-store-alt'></i></div>
                <div class="menu-title">Vendors
                    @if(($pendingVendorCount ?? 0) > 0)
                        <span class="badge bg-warning text-dark ms-1">{{ $pendingVendorCount }}</span>
                    @endif
                </div>
            </a>
        </li>

        <li>
            <a href="{{ route('user.records') }}" class="{{ request()->routeIs('user.records') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-group'></i></div>
                <div class="menu-title">Users</div>
            </a>
        </li>

        <li>
            <a href="{{ route('featured.records') }}" class="{{ request()->routeIs('featured.records') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-star'></i></div>
                <div class="menu-title">Featured</div>
            </a>
        </li>

        <li class="menu-label">Catalog</li>

        <li>
            <a href="{{ route('category.records') }}" class="{{ request()->routeIs('category.records') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-category'></i></div>
                <div class="menu-title">Categories</div>
            </a>
        </li>

        <li>
            <a href="{{ route('sub.category.records') }}" class="{{ request()->routeIs('sub.category.records') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-subdirectory-right'></i></div>
                <div class="menu-title">Sub Categories</div>
            </a>
        </li>

        <li>
            <a href="{{ route('company.records') }}" class="{{ request()->routeIs('company.records') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-buildings'></i></div>
                <div class="menu-title">Companies</div>
            </a>
        </li>

        <li>
            <a href="{{ route('product.category.records') }}" class="{{ request()->routeIs('product.category.records') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-purchase-tag'></i></div>
                <div class="menu-title">Product Categories</div>
            </a>
        </li>

        <li>
            <a href="{{ route('product.records') }}" class="{{ request()->routeIs('product.records') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-box'></i></div>
                <div class="menu-title">Products</div>
            </a>
        </li>

        <li class="menu-label">Finance</li>

        <li>
            <a href="{{ route('earning.records') }}" class="{{ request()->routeIs('earning.records') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-wallet'></i></div>
                <div class="menu-title">Earnings</div>
            </a>
        </li>

        <li class="menu-label">Services</li>

        <li>
            <a href="{{ route('admin.service.requests') }}" class="{{ request()->routeIs('admin.service.requests') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-wrench'></i></div>
                <div class="menu-title">
                    Service Requests
                    @if(($openServiceRequestCount ?? 0) > 0)
                        <span class="badge bg-warning text-dark ms-1">{{ $openServiceRequestCount }}</span>
                    @endif
                </div>
            </a>
        </li>

        <li>
            <a href="{{ route('admin.services') }}" class="{{ request()->routeIs('admin.services') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-briefcase-alt'></i></div>
                <div class="menu-title">Services</div>
            </a>
        </li>

        <li class="menu-label">Moderation</li>

        <li>
            <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-shield-quarter'></i></div>
                <div class="menu-title">
                    Reported Content
                    @if(($pendingReportCount ?? 0) > 0)
                        <span class="badge bg-danger ms-1">{{ $pendingReportCount }}</span>
                    @endif
                </div>
            </a>
        </li>

        <li>
            <a href="{{ route('admin.reviews') }}" class="{{ request()->routeIs('admin.reviews') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-message-square-detail'></i></div>
                <div class="menu-title">
                    Product Reviews
                    @if(($pendingReviewCount ?? 0) > 0)
                        <span class="badge bg-warning ms-1">{{ $pendingReviewCount }}</span>
                    @endif
                </div>
            </a>
        </li>

        <li class="menu-label">Boosts</li>

        <li>
            <a href="{{ route('admin.boosts') }}" class="{{ request()->routeIs('admin.boosts') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-trending-up'></i></div>
                <div class="menu-title">
                    Boost Requests
                    @if(($pendingBoostCount ?? 0) > 0)
                        <span class="badge bg-danger ms-1">{{ $pendingBoostCount }}</span>
                    @endif
                </div>
            </a>
        </li>

        <li class="menu-label">System</li>

        <li>
            <a href="{{ route('boost.packages') }}" class="{{ request()->routeIs('boost.packages') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-rocket'></i></div>
                <div class="menu-title">Boost Packages</div>
            </a>
        </li>

        <li>
            <a href="{{ route('admin.legal') }}" class="{{ request()->routeIs('admin.legal') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-shield'></i></div>
                <div class="menu-title">Legal &amp; Support</div>
            </a>
        </li>

        <li>
            <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings') ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-cog'></i></div>
                <div class="menu-title">Settings</div>
            </a>
        </li>

        <li class="menu-label">Account</li>

        <li>
            <a href="{{ route('admin.profile') }}" class="{{ request()->routeIs('admin.profile') && request('tab') !== 'password' ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-user-circle'></i></div>
                <div class="menu-title">My Profile</div>
            </a>
        </li>

        <li>
            <a href="{{ route('admin.profile', ['tab' => 'password']) }}" class="{{ request()->routeIs('admin.profile') && request('tab') === 'password' ? 'mm-active' : '' }}">
                <div class="parent-icon"><i class='bx bx-lock-alt'></i></div>
                <div class="menu-title">Change Password</div>
            </a>
        </li>

        <li>
            <a href="javascript:;" class="sidebar-logout" onclick="document.getElementById('sidebar-logout-form').submit()">
                <div class="parent-icon"><i class='bx bx-log-out-circle'></i></div>
                <div class="menu-title">Logout</div>
            </a>
            <form id="sidebar-logout-form" method="POST" action="{{ route('admin.logout') }}" class="d-none">
                @csrf
            </form>
        </li>

    </ul>
    <!--end navigation-->
</div>
<!--end sidebar wrapper -->
