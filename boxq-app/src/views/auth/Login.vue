<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import api from '../../services/api';
import AuthLayout from '../../layouts/AuthLayout.vue';

const router = useRouter();
const form = reactive({
    email: '',
    password: ''
});
const errorMessage = ref('');

const handleLogin = async () => {
    try {
        errorMessage.value = '';
        const response = await api.post('/login', form);
        
        localStorage.setItem('token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        router.push('/');
    } catch (error) {
        if (axios.isAxiosError(error) && error.response && error.response.status === 422) {
            errorMessage.value = "Incorrect email or password.";
        } else {
            errorMessage.value = "Server error. Please try again.";
        }
    }
};
</script>

<script lang="ts">
export default {
    name: 'LoginView'
}
</script>

<template>
    <AuthLayout>
        <div class="card shadow-sm border-0" style="width: 400px;">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <i class="fa-solid fa-box-open fa-3x mb-3 text-dark"></i>
                    <h4 class="fw-bold">Welcome Back</h4>
                    <p class="text-muted small">Sign in to access BoxQ Procurement.</p>
                </div>

                <div v-if="errorMessage" class="alert alert-danger py-2 small text-center">
                    {{ errorMessage }}
                </div>

                <form @submit.prevent="handleLogin">
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-bold text-uppercase">Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fa-regular fa-envelope text-muted"></i></span>
                            <input v-model="form.email" type="email" class="form-control border-start-0 ps-0" placeholder="name@company.com" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-bold text-uppercase">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input v-model="form.password" type="password" class="form-control border-start-0 ps-0" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-2 mb-3 fw-medium">Sign In</button>
                    
                    <div class="text-center">
                        <span class="text-muted small">New employee? </span>
                        <RouterLink to="/register" class="text-decoration-none fw-bold text-dark">Create Account</RouterLink>
                    </div>
                </form>
            </div>
        </div>
    </AuthLayout>
</template>