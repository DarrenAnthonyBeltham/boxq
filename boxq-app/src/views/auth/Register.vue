<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../services/api';
import AuthLayout from '../../layouts/AuthLayout.vue';

const router = useRouter();
const form = reactive({
    name: '',
    email: '',
    department: '',
    password: ''
});
const errorMessage = ref('');

const handleRegister = async () => {
    try {
        errorMessage.value = '';
        const response = await api.post('/register', form);
        
        localStorage.setItem('token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        router.push('/');
    } catch (error) {
        errorMessage.value = "Registration failed. Email might be in use.";
    }
};
</script>

<script lang="ts">
export default {
    name: 'RegisterView'
}
</script>

<template>
    <AuthLayout>
        <div class="card shadow-sm border-0" style="width: 450px;">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h4 class="fw-bold">Create Account</h4>
                    <p class="text-muted small">Join your team on BoxQ.</p>
                </div>

                <div v-if="errorMessage" class="alert alert-danger py-2 small text-center">
                    {{ errorMessage }}
                </div>

                <form @submit.prevent="handleRegister">
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-bold">Full Name</label>
                        <input v-model="form.name" type="text" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-bold">Work Email</label>
                        <input v-model="form.email" type="email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-bold">Department</label>
                        <select v-model="form.department" class="form-select text-muted" required>
                            <option value="" disabled selected>Select Department</option>
                            <option value="Engineering">Engineering</option>
                            <option value="Finance">Finance</option>
                            <option value="Marketing">Marketing</option>
                            <option value="HR">Human Resources</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-bold">Password</label>
                        <input v-model="form.password" type="password" class="form-control" minlength="8" required>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-2 mb-3">Register</button>
                    
                    <div class="text-center">
                        <span class="text-muted small">Already have an account? </span>
                        <RouterLink to="/login" class="text-decoration-none fw-bold text-dark">Sign In</RouterLink>
                    </div>
                </form>
            </div>
        </div>
    </AuthLayout>
</template>