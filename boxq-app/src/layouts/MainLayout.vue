<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';

const router = useRouter();
const user = ref({
    name: 'Loading...',
    department: '',
    role: ''
});

onMounted(() => {
    const userData = localStorage.getItem('user');
    if (userData) {
        user.value = JSON.parse(userData);
    }
});

const getInitials = (name: string) => {
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
};

const logout = async () => {
    try {
        await api.post('/logout');
    } catch (e) {
        console.error(e);
    } finally {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        router.push('/login');
    }
};
</script>

<template>
    <div class="d-flex min-vh-100">
        <div class="bg-dark text-white p-3 d-flex flex-column" style="width: 250px;">
            <div class="mb-4 px-2">
                <h4 class="fw-bold"><i class="fa-solid fa-box-open me-2"></i>BoxQ</h4>
            </div>
            
            <ul class="nav flex-column flex-grow-1">
                <li class="nav-item mb-2">
                    <RouterLink to="/" class="nav-link text-white active">
                        <i class="fa-solid fa-gauge me-2" style="width: 20px;"></i> Dashboard
                    </RouterLink>
                </li>
                <li class="nav-item mb-2">
                    <RouterLink to="/create" class="nav-link text-white-50">
                        <i class="fa-solid fa-plus me-2" style="width: 20px;"></i> New Request
                    </RouterLink>
                </li>
            </ul>

            <div class="mt-auto pt-3 border-top border-secondary">
                <div class="d-flex align-items-center px-2">
                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                        <span class="small fw-bold text-white">{{ getInitials(user.name) }}</span>
                    </div>
                    <div class="small">
                        <div class="fw-bold">{{ user.name }}</div>
                        <div class="text-white-50 text-capitalize" style="font-size: 0.75rem;">
                            {{ user.department }} • {{ user.role }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-grow-1 bg-light d-flex flex-column">
            <nav class="navbar navbar-light bg-white border-bottom px-4 py-3">
                <span class="navbar-text text-secondary">
                    <i class="fa-regular fa-calendar me-1"></i> Today is {{ new Date().toLocaleDateString() }}
                </span>
                <button @click="logout" class="btn btn-outline-danger btn-sm">
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
.nav-link:hover {
    background-color: rgba(255,255,255,0.1);
    border-radius: 5px;
    color: white !important;
}
.router-link-active {
    background-color: #0d6efd;
    border-radius: 5px;
    color: white !important;
}
</style>