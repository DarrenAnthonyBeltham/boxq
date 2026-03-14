<template>
    <div class="container mt-4">
        <h3 class="fw-bold text-dark mb-4 text-center text-md-start">Account Settings</h3>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3 p-md-4">
                <h5 class="fw-bold mb-3">Vacation Delegation</h5>
                <p class="text-muted mb-4">Temporarily route your approval requests to another manager while you are away.</p>

                <form @submit.prevent="saveDelegation">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Delegate Approvals To</label>
                            <select v-model="form.delegated_to_id" class="form-select">
                                <option value="">-- Remove Delegation --</option>
                                <option v-for="u in filteredUsers" :key="u._id || u.id" :value="u._id || u.id">
                                    {{ u.name }} ({{ u.department }})
                                </option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Start Date</label>
                            <input type="date" v-model="form.delegation_start" class="form-control" :required="form.delegated_to_id !== ''">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">End Date</label>
                            <input type="date" v-model="form.delegation_end" class="form-control" :required="form.delegated_to_id !== ''">
                        </div>
                    </div>

                    <div v-if="successMsg" class="alert alert-success mt-3 py-2">
                        {{ successMsg }}
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-dark px-4 py-2" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

interface User {
    _id?: string;
    id?: string;
    name: string;
    department: string;
    delegated_to_id?: string | null;
    delegation_start?: string | null;
    delegation_end?: string | null;
}

const users = ref<User[]>([]);
const loading = ref(false);
const successMsg = ref('');
const currentUser = ref<User>(JSON.parse(localStorage.getItem('user') || '{}'));

const form = ref({
    delegated_to_id: currentUser.value.delegated_to_id || '',
    delegation_start: currentUser.value.delegation_start ? currentUser.value.delegation_start.split('T')[0] : '',
    delegation_end: currentUser.value.delegation_end ? currentUser.value.delegation_end.split('T')[0] : ''
});

const filteredUsers = computed(() => {
    return users.value.filter(u => (u._id || u.id) !== (currentUser.value._id || currentUser.value.id));
});

const fetchUsers = async () => {
    try {
        const token = localStorage.getItem('token');
        const response = await axios.get('http://127.0.0.1:8000/api/users', {
            headers: { Authorization: `Bearer ${token}` }
        });
        users.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

const saveDelegation = async () => {
    loading.value = true;
    successMsg.value = '';
    try {
        const token = localStorage.getItem('token');
        const response = await axios.post('http://127.0.0.1:8000/api/user/delegate', form.value, {
            headers: { Authorization: `Bearer ${token}` }
        });
        
        localStorage.setItem('user', JSON.stringify(response.data));
        currentUser.value = response.data;
        successMsg.value = 'Delegation settings updated successfully!';
        
        setTimeout(() => { successMsg.value = ''; }, 3000);
    } catch (error) {
        console.error(error);
        alert('Failed to save settings.');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchUsers();
});
</script>