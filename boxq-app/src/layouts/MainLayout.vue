<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';

const router = useRouter();
const user = ref({
    name: 'Loading...',
    department: '',
    role: '',
    avatar: ''
});

const loadUser = () => {
    try {
        const userData = localStorage.getItem('user');
        if (userData) {
            user.value = JSON.parse(userData);
        }
    } catch {
        console.warn("Corrupted user data in storage.");
    }
};

onMounted(() => {
    loadUser();
    window.addEventListener('user-updated', loadUser);
});

onUnmounted(() => {
    window.removeEventListener('user-updated', loadUser);
});

const getInitials = (name?: string) => {
    if (!name || name === 'Loading...') return '';
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
};

const logout = async () => {
    try {
        await api.post('/logout');
    } catch (error) {
        console.error(error);
    } finally {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        router.push('/login');
    }
};
</script>

<template>
    <div class="d-flex w-100 min-vh-100 overflow-hidden">
        <div class="bg-dark text-white d-flex flex-column flex-shrink-0" style="width: 260px; min-width: 260px; z-index: 1000;">
            <div class="p-4 border-bottom border-secondary border-opacity-25">
                <h4 class="fw-bold mb-0"><i class="fa-solid fa-box-open me-2 text-primary"></i>BoxQ</h4>
            </div>
            
            <div class="p-3 flex-grow-1 overflow-auto">
                <ul class="nav flex-column gap-1">
                    <li class="nav-item">
                        <RouterLink to="/" class="nav-link custom-nav-link" exact-active-class="active-link">
                            <i class="fa-solid fa-gauge fa-fw me-2"></i> Dashboard
                        </RouterLink>
                    </li>
                    <li class="nav-item">
                        <RouterLink to="/catalog" class="nav-link custom-nav-link" exact-active-class="active-link">
                            <i class="fa-solid fa-book fa-fw me-2"></i> Catalog
                        </RouterLink>
                    </li>
                    <li v-if="user?.role !== 'finance'" class="nav-item">
                        <RouterLink to="/create" class="nav-link custom-nav-link" exact-active-class="active-link">
                            <i class="fa-solid fa-plus fa-fw me-2"></i> New Request
                        </RouterLink>
                    </li>
                    <li v-if="user?.role === 'admin'" class="nav-item mt-2 pt-2 border-top border-secondary border-opacity-25">
                        <RouterLink to="/admin/products" class="nav-link custom-nav-link" exact-active-class="active-link">
                            <i class="fa-solid fa-boxes-stacked fa-fw me-2"></i> Manage Products
                        </RouterLink>
                    </li>
                </ul>
            </div>

            <div class="p-3 border-top border-secondary border-opacity-25">
                <RouterLink to="/profile" class="text-decoration-none d-flex align-items-center p-2 rounded profile-link">
                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3 overflow-hidden flex-shrink-0" style="width: 36px; height: 36px;">
                        <img v-if="user?.avatar" :src="`http://127.0.0.1:8000${user.avatar}`" class="w-100 h-100 object-fit-cover">
                        <span v-else class="small fw-bold text-white">{{ getInitials(user?.name) }}</span>
                    </div>
                    <div class="small text-truncate text-white">
                        <div class="fw-bold">{{ user?.name || 'Unknown' }}</div>
                        <div class="text-white-50 text-capitalize" style="font-size: 0.75rem;">
                            {{ user?.department || 'N/A' }} • {{ user?.role || 'User' }}
                        </div>
                    </div>
                </RouterLink>
            </div>
        </div>

        <div class="d-flex flex-column flex-grow-1 bg-light" style="min-width: 0;">
            <nav class="navbar navbar-light bg-white border-bottom px-4 py-3 flex-shrink-0 d-flex justify-content-between">
                <span class="text-secondary small fw-medium">
                    <i class="fa-regular fa-calendar me-1"></i> Today is {{ new Date().toLocaleDateString() }}
                </span>
                <button @click="logout" class="btn btn-outline-danger btn-sm px-3 fw-medium">
                    <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                </button>
            </nav>
            
            <div class="p-4 flex-grow-1 overflow-auto">
                <slot></slot>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-nav-link {
    color: #adb5bd;
    border-radius: 0.375rem;
    padding: 0.6rem 1rem;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    white-space: nowrap;
}
.custom-nav-link:hover {
    color: #fff;
    background-color: rgba(255, 255, 255, 0.05);
}
.active-link {
    background-color: #0d6efd !important;
    color: #fff !important;
    font-weight: 500;
    box-shadow: 0 4px 6px -1px rgba(13, 110, 253, 0.2);
}
.profile-link:hover {
    background-color: rgba(255, 255, 255, 0.05);
    transition: background-color 0.2s;
}
</style>