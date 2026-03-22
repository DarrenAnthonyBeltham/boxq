<script setup lang="ts">
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';

interface User {
    name: string;
    role: string;
    department: string;
}

interface Notification {
    _id: string;
    title: string;
    message: string;
    type: string;
    link: string;
    is_read: boolean;
    created_at: string;
}

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const currentUser = ref<User>({ name: '', role: '', department: '' });
const notifications = ref<Notification[]>([]);
let notificationInterval: ReturnType<typeof setInterval> | null = null;

const showUserMenu = ref(false);
const showNotifMenu = ref(false);

const toggleUserMenu = () => {
    showNotifMenu.value = false;
    showUserMenu.value = !showUserMenu.value;
};

const toggleNotifMenu = () => {
    showUserMenu.value = false;
    showNotifMenu.value = !showNotifMenu.value;
};

const closeMenus = (e: MouseEvent) => {
    const target = e.target as HTMLElement;
    if (!target.closest('.user-menu-container')) {
        showUserMenu.value = false;
    }
    if (!target.closest('.notif-menu-container')) {
        showNotifMenu.value = false;
    }
};

const fetchNotifications = async () => {
    try {
        const response = await api.get('/notifications');
        notifications.value = response.data;
    } catch (error) {
    }
};

const markAllAsRead = async () => {
    try {
        await api.post('/notifications/read');
        notifications.value = notifications.value.map(n => ({ ...n, is_read: true }));
        showNotifMenu.value = false;
    } catch (error) {
    }
};

onMounted(() => {
    document.addEventListener('click', closeMenus);
    const userData = localStorage.getItem('user');
    if (userData) {
        currentUser.value = JSON.parse(userData);
        fetchNotifications();
        notificationInterval = setInterval(fetchNotifications, 15000); 
    }
});

onUnmounted(() => {
    document.removeEventListener('click', closeMenus);
    if (notificationInterval) {
        clearInterval(notificationInterval);
    }
});

const unreadCount = computed(() => {
    return notifications.value.filter(n => !n.is_read).length;
});

const getIconForType = (type: string) => {
    const map: Record<string, string> = {
        'info': 'fa-file-invoice text-primary',
        'warning': 'fa-triangle-exclamation text-warning',
        'success': 'fa-check text-success',
        'danger': 'fa-xmark text-danger'
    };
    return map[type] || 'fa-bell text-secondary';
};

const getBgForType = (type: string) => {
    const map: Record<string, string> = {
        'info': 'bg-primary bg-opacity-10',
        'warning': 'bg-warning bg-opacity-10',
        'success': 'bg-success bg-opacity-10',
        'danger': 'bg-danger bg-opacity-10'
    };
    return map[type] || 'bg-secondary bg-opacity-10';
};

const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' - ' + date.toLocaleDateString();
};

const handleNotificationClick = async (notif: Notification) => {
    showNotifMenu.value = false;
    
    if (!notif.is_read) {
        try {
            await api.post('/notifications/read');
            notifications.value = notifications.value.map(n => ({ ...n, is_read: true }));
        } catch (e) {}
    }

    const correctLink = notif.link.replace('/requisitions/', '/requisition/');
    router.push(correctLink);
};

const handleLogout = async () => {
    showUserMenu.value = false;
    await authStore.logout();
    router.push('/login');
};

const isActive = (path: string) => route.path === path;
const isPrefixActive = (path: string) => route.path.startsWith(path) && path !== '/';
</script>

<script lang="ts">
export default { name: 'MainLayout' }
</script>

<template>
    <div class="d-flex" style="min-height: 100vh; background-color: #f4f6f9;">
        <aside class="sidebar position-fixed top-0 bottom-0 d-flex flex-column" style="width: 260px; background-color: #1e1e2d; z-index: 1040;">
            <div class="p-4 d-flex align-items-center mt-2">
                <i class="fa-solid fa-box-open text-primary fs-2 me-3"></i>
                <h3 class="fw-bold mb-0 text-white tracking-wide">BoxQ</h3>
            </div>

            <div class="px-4 mb-4">
                <hr class="border-secondary opacity-25 m-0">
            </div>

            <div class="px-3 flex-grow-1" style="overflow-y: auto; overflow-x: hidden;">
                <ul class="nav flex-column gap-2">
                    <li class="nav-item">
                        <router-link to="/" class="nav-link rounded-3 px-3 py-2 d-flex align-items-center transition-all" :class="isActive('/') ? 'bg-primary text-white shadow-sm' : 'text-secondary text-opacity-75 hover-text-white'">
                            <i class="fa-solid fa-chart-pie fs-5 me-3" style="width: 24px; text-align: center;"></i>
                            <span class="fw-medium fs-6">Dashboard</span>
                        </router-link>
                    </li>
                    
                    <li v-if="['admin', 'finance', 'manager'].includes(currentUser.role)" class="nav-item">
                        <router-link to="/analytics" class="nav-link rounded-3 px-3 py-2 d-flex align-items-center transition-all" :class="isActive('/analytics') ? 'bg-primary text-white shadow-sm' : 'text-secondary text-opacity-75 hover-text-white'">
                            <i class="fa-solid fa-chart-line fs-5 me-3" style="width: 24px; text-align: center;"></i>
                            <span class="fw-medium fs-6">Analytics</span>
                        </router-link>
                    </li>

                    <li class="nav-item mt-2">
                        <router-link to="/catalog" class="nav-link rounded-3 px-3 py-2 d-flex align-items-center transition-all" :class="isActive('/catalog') ? 'bg-primary text-white shadow-sm' : 'text-secondary text-opacity-75 hover-text-white'">
                            <i class="fa-solid fa-book-open fs-5 me-3" style="width: 24px; text-align: center;"></i>
                            <span class="fw-medium fs-6">Catalog</span>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/create" class="nav-link rounded-3 px-3 py-2 d-flex align-items-center transition-all" :class="isActive('/create') ? 'bg-primary text-white shadow-sm' : 'text-secondary text-opacity-75 hover-text-white'">
                            <i class="fa-solid fa-plus fs-5 me-3" style="width: 24px; text-align: center;"></i>
                            <span class="fw-medium fs-6">New Request</span>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/vendors" class="nav-link rounded-3 px-3 py-2 d-flex align-items-center transition-all" :class="isActive('/vendors') ? 'bg-primary text-white shadow-sm' : 'text-secondary text-opacity-75 hover-text-white'">
                            <i class="fa-regular fa-calendar fs-5 me-3" style="width: 24px; text-align: center;"></i>
                            <span class="fw-medium fs-6">Vendors</span>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/purchase-orders" class="nav-link rounded-3 px-3 py-2 d-flex align-items-center transition-all" :class="isActive('/purchase-orders') ? 'bg-primary text-white shadow-sm' : 'text-secondary text-opacity-75 hover-text-white'">
                            <i class="fa-solid fa-file-invoice fs-5 me-3" style="width: 24px; text-align: center;"></i>
                            <span class="fw-medium fs-6">Purchase Orders</span>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/receipts" class="nav-link rounded-3 px-3 py-2 d-flex align-items-center transition-all" :class="isActive('/receipts') ? 'bg-primary text-white shadow-sm' : 'text-secondary text-opacity-75 hover-text-white'">
                            <i class="fa-solid fa-box fs-5 me-3" style="width: 24px; text-align: center;"></i>
                            <span class="fw-medium fs-6">Receiving</span>
                        </router-link>
                    </li>

                    <li v-if="['admin'].includes(currentUser.role)" class="nav-item mt-4">
                        <div class="px-3 mb-2 text-uppercase text-secondary small fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Admin</div>
                        <router-link to="/admin/budgets" class="nav-link rounded-3 px-3 py-2 d-flex align-items-center transition-all" :class="isPrefixActive('/admin/budgets') ? 'bg-primary text-white shadow-sm' : 'text-secondary text-opacity-75 hover-text-white'">
                            <i class="fa-solid fa-wallet fs-5 me-3" style="width: 24px; text-align: center;"></i>
                            <span class="fw-medium fs-6">Budgets</span>
                        </router-link>
                    </li>
                    <li v-if="['admin'].includes(currentUser.role)" class="nav-item">
                        <router-link to="/admin/products" class="nav-link rounded-3 px-3 py-2 d-flex align-items-center transition-all" :class="isPrefixActive('/admin/products') ? 'bg-primary text-white shadow-sm' : 'text-secondary text-opacity-75 hover-text-white'">
                            <i class="fa-solid fa-tags fs-5 me-3" style="width: 24px; text-align: center;"></i>
                            <span class="fw-medium fs-6">Products</span>
                        </router-link>
                    </li>
                </ul>
            </div>

            <div class="p-3 mt-auto border-top border-secondary border-opacity-25">
                <div class="user-menu-container position-relative w-100">
                    <button @click.stop="toggleUserMenu" class="btn btn-dark w-100 border-0 d-flex align-items-center justify-content-between p-3 rounded-3 shadow-sm" type="button" style="background-color: #2b2b40;">
                        <div class="d-flex align-items-center overflow-hidden">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold me-3 flex-shrink-0" style="width: 38px; height: 38px;">
                                {{ currentUser.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="text-start text-truncate">
                                <div class="fw-bold text-white text-truncate fs-6" style="line-height: 1.2;">{{ currentUser.name }}</div>
                                <div class="text-secondary small text-truncate text-capitalize" style="font-size: 0.75rem;">{{ currentUser.role }}</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-up text-secondary ms-2 fs-7"></i>
                    </button>
                    <ul v-show="showUserMenu" class="dropdown-menu dropdown-menu-dark shadow border-0 w-100 mb-2 show" style="position: absolute; bottom: 100%; left: 0;">
                        <li>
                            <button @click="router.push('/profile'); showUserMenu = false" class="dropdown-item py-2 d-flex align-items-center text-white w-100 text-start bg-transparent border-0 hover-bg-dark">
                                <i class="fa-regular fa-user me-3 w-15px"></i> My Profile
                            </button>
                        </li>
                        <li>
                            <button @click="router.push('/settings'); showUserMenu = false" class="dropdown-item py-2 d-flex align-items-center text-white w-100 text-start bg-transparent border-0 hover-bg-dark">
                                <i class="fa-solid fa-gear me-3 w-15px"></i> Settings
                            </button>
                        </li>
                        <li><hr class="dropdown-divider border-secondary opacity-25"></li>
                        <li>
                            <button @click="handleLogout" class="dropdown-item py-2 text-danger d-flex align-items-center fw-bold w-100 text-start border-0 bg-transparent hover-bg-dark">
                                <i class="fa-solid fa-right-from-bracket me-3 w-15px"></i> Sign Out
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>

        <main class="flex-grow-1 position-relative" style="margin-left: 260px;">
            <header class="bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center sticky-top z-3">
                <div class="d-flex align-items-center">
                    <button class="btn btn-light d-lg-none me-3"><i class="fa-solid fa-bars"></i></button>
                    <h5 class="fw-bold text-dark mb-0 d-none d-md-block">{{ route.name?.toString().replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}</h5>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="notif-menu-container position-relative">
                        <button @click.stop="toggleNotifMenu" class="btn btn-light position-relative rounded-circle p-2" type="button" style="width: 40px; height: 40px;">
                            <i class="fa-regular fa-bell"></i>
                            <span v-if="unreadCount > 0" class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                                <span class="visually-hidden">New alerts</span>
                            </span>
                        </button>
                        <ul v-show="showNotifMenu" class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-0 overflow-hidden show" style="position: absolute; top: 100%; right: 0; width: 320px;">
                            <li class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-dark">Notifications <span v-if="unreadCount > 0" class="badge bg-danger ms-1">{{ unreadCount }}</span></h6>
                            </li>
                            <li>
                                <ul class="list-unstyled mb-0" style="max-height: 350px; overflow-y: auto;">
                                    <li v-if="notifications.length === 0" class="p-4 text-center text-muted small">
                                        You have no notifications.
                                    </li>
                                    <li v-for="notif in notifications" :key="notif._id" class="border-bottom">
                                        <button @click="handleNotificationClick(notif)" class="dropdown-item py-3 px-3 text-wrap w-100 text-start border-0 hover-bg-light" :class="notif.is_read ? 'bg-white' : 'bg-light'">
                                            <div class="d-flex align-items-start">
                                                <div class="rounded-circle p-2 me-3 mt-1" :class="getBgForType(notif.type)">
                                                    <i class="fa-solid" :class="getIconForType(notif.type)" style="width: 16px; text-align: center;"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-1 small text-dark" :class="notif.is_read ? '' : 'fw-bold'">{{ notif.title }}</p>
                                                    <p class="mb-1 text-muted" style="font-size: 0.8rem;">{{ notif.message }}</p>
                                                    <p class="mb-0 text-secondary" style="font-size: 0.7rem;">{{ formatTime(notif.created_at) }}</p>
                                                </div>
                                            </div>
                                        </button>
                                    </li>
                                </ul>
                            </li>
                            <li v-if="unreadCount > 0" class="p-2 border-top bg-light">
                                <button @click="markAllAsRead" class="btn btn-link text-decoration-none w-100 text-center small fw-bold text-primary py-1 border-0 bg-transparent">Mark all as read</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <div class="p-4 p-md-5">
                <slot></slot>
            </div>
        </main>
    </div>
</template>

<style scoped>
.hover-text-white:hover {
    color: #ffffff !important;
    background-color: rgba(255, 255, 255, 0.05);
}
.hover-bg-dark:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
}
.hover-bg-light:hover {
    background-color: #f8fafc !important;
}
.w-15px {
    width: 15px;
    text-align: center;
}
.transition-all {
    transition: all 0.2s ease-in-out;
}
</style>