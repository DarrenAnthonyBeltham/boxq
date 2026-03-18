<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';

interface User {
    name: string;
    role: string;
    department: string;
}

const route = useRoute();
const router = useRouter();
const currentUser = ref<User>({ name: '', role: '', department: '' });

onMounted(() => {
    const userData = localStorage.getItem('user');
    if (userData) {
        currentUser.value = JSON.parse(userData);
    }
});

const logout = async () => {
    try {
        await api.post('/logout');
    } catch (error) {
    } finally {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        router.push('/login');
    }
};

const isActive = (path: string) => route.path === path;
const isPrefixActive = (path: string) => route.path.startsWith(path) && path !== '/';
</script>

<script lang="ts">
export default {
    name: 'MainLayout'
}
</script>

<template>
    <div class="d-flex" style="min-height: 100vh; background-color: #f4f6f9;">
        <aside class="sidebar position-fixed top-0 bottom-0 d-flex flex-column" style="width: 260px; background-color: #1e1e2d; z-index: 1040; overflow-y: auto;">
            <div class="p-4 d-flex align-items-center mt-2">
                <i class="fa-solid fa-box-open text-primary fs-2 me-3"></i>
                <h3 class="fw-bold mb-0 text-white tracking-wide">BoxQ</h3>
            </div>

            <div class="px-4 mb-4">
                <hr class="border-secondary opacity-25 m-0">
            </div>

            <div class="px-3 flex-grow-1">
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

                    <li v-if="['admin', 'finance'].includes(currentUser.role)" class="nav-item mt-4">
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

            <div class="p-3 mt-auto">
                <div class="dropdown">
                    <button class="btn btn-dark w-100 border-0 d-flex align-items-center justify-content-between p-3 rounded-3 shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #2b2b40;">
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
                    <ul class="dropdown-menu dropdown-menu-dark shadow border-0 w-100 mb-2">
                        <li>
                            <router-link to="/profile" class="dropdown-item py-2 d-flex align-items-center">
                                <i class="fa-regular fa-user me-3 w-15px"></i> My Profile
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/settings" class="dropdown-item py-2 d-flex align-items-center">
                                <i class="fa-solid fa-gear me-3 w-15px"></i> Settings
                            </router-link>
                        </li>
                        <li><hr class="dropdown-divider border-secondary opacity-25"></li>
                        <li>
                            <button @click="logout" class="dropdown-item py-2 text-danger d-flex align-items-center fw-bold">
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
                    <button class="btn btn-light position-relative rounded-circle p-2" style="width: 40px; height: 40px;">
                        <i class="fa-regular fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                            <span class="visually-hidden">New alerts</span>
                        </span>
                    </button>
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
.w-15px {
    width: 15px;
    text-align: center;
}
.transition-all {
    transition: all 0.2s ease-in-out;
}
</style>