<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface Colleague {
    _id?: string | { $oid: string };
    id?: string;
    name: string;
    department: string;
}

const currentUser = ref({
    _id: '',
    id: '',
    name: '',
    role: '',
    preferences: {
        email_on_status: true,
        email_on_new: false
    },
    delegated_to_id: '',
    delegation_start: '',
    delegation_end: ''
});

const prefForm = reactive({
    email_on_status: true,
    email_on_new: false
});

const delegationForm = reactive({
    delegated_to_id: '',
    delegation_start: '',
    delegation_end: ''
});

const colleagues = ref<Colleague[]>([]);
const isSavingPrefs = ref(false);
const isSavingDelegation = ref(false);

const prefSuccess = ref('');
const delegationSuccess = ref('');

interface Identifiable {
    _id?: string | { $oid: string };
    id?: string;
}

const getSafeId = (item: Identifiable) => {
    if (item.id) return String(item.id);
    if (item._id) return typeof item._id === 'object' ? String(item._id.$oid) : String(item._id);
    return Math.random().toString();
};

const filteredColleagues = computed(() => {
    const currentId = getSafeId(currentUser.value);
    return colleagues.value.filter(c => getSafeId(c) !== currentId);
});

onMounted(() => {
    const userData = localStorage.getItem('user');
    if (userData) {
        const parsed = JSON.parse(userData);
        currentUser.value = parsed;
        
        if (parsed.preferences) {
            prefForm.email_on_status = parsed.preferences.email_on_status;
            prefForm.email_on_new = parsed.preferences.email_on_new;
        }

        delegationForm.delegated_to_id = parsed.delegated_to_id || '';
        delegationForm.delegation_start = parsed.delegation_start ? parsed.delegation_start.split('T')[0] : '';
        delegationForm.delegation_end = parsed.delegation_end ? parsed.delegation_end.split('T')[0] : '';

        if (parsed.role === 'manager' || parsed.role === 'admin') {
            fetchColleagues();
        }
    }
});

const fetchColleagues = async () => {
    try {
        const response = await api.get('/users');
        colleagues.value = response.data;
    } catch (error) {
    }
};

const savePreferences = async () => {
    isSavingPrefs.value = true;
    prefSuccess.value = '';

    try {
        const response = await api.put('/user/preferences', { preferences: prefForm });
        prefSuccess.value = 'Preferences saved successfully.';
        
        currentUser.value = response.data.user;
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        setTimeout(() => prefSuccess.value = '', 3000);
    } catch (error) {
        alert("Failed to save preferences.");
    } finally {
        isSavingPrefs.value = false;
    }
};

const saveDelegation = async () => {
    isSavingDelegation.value = true;
    delegationSuccess.value = '';

    try {
        const response = await api.post('/user/delegate', delegationForm);
        delegationSuccess.value = 'Delegation settings updated successfully.';
        
        currentUser.value = response.data;
        localStorage.setItem('user', JSON.stringify(response.data));
        
        setTimeout(() => delegationSuccess.value = '', 3000);
    } catch (error) {
        alert("Failed to save delegation settings.");
    } finally {
        isSavingDelegation.value = false;
    }
};
</script>

<script lang="ts">
export default { name: 'AccountSettings' }
</script>

<template>
    <MainLayout>
        <div class="mb-4 d-flex justify-content-between align-items-end">
            <div>
                <h3 class="fw-bold text-dark mb-1">Application Settings</h3>
                <p class="text-muted mb-0">Configure your notifications and workflow automation.</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="text-uppercase text-muted small fw-bold mb-0">Notification Preferences</h6>
                        <span v-if="prefSuccess" class="text-success small fw-bold">
                            <i class="fa-solid fa-check me-1"></i> Saved
                        </span>
                    </div>

                    <div class="list-group list-group-flush mb-4 border-bottom border-top">
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-0">
                            <div>
                                <h6 class="mb-1 fw-bold text-dark">Status Updates</h6>
                                <p class="mb-0 small text-muted">Email me when my requests are approved, rejected, or paid.</p>
                            </div>
                            <div class="form-check form-switch fs-4">
                                <input v-model="prefForm.email_on_status" class="form-check-input" type="checkbox" role="switch">
                            </div>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-0 border-bottom-0">
                            <div>
                                <h6 class="mb-1 fw-bold text-dark">New Requisitions</h6>
                                <p class="mb-0 small text-muted">Email me when a new request requires my attention.</p>
                            </div>
                            <div class="form-check form-switch fs-4">
                                <input v-model="prefForm.email_on_new" class="form-check-input" type="checkbox" role="switch">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button @click="savePreferences" class="btn btn-dark px-4" :disabled="isSavingPrefs">
                            <span v-if="isSavingPrefs" class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Save Preferences
                        </button>
                    </div>
                </div>

                <div v-if="currentUser.role === 'manager' || currentUser.role === 'admin'" class="card border-0 shadow-sm p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h6 class="text-uppercase text-muted small fw-bold mb-1">Out of Office Delegation</h6>
                            <p class="mb-0 small text-muted">Temporarily route your approval requests to another manager while you are away.</p>
                        </div>
                        <span v-if="delegationSuccess" class="text-success small fw-bold">
                            <i class="fa-solid fa-check me-1"></i> Saved
                        </span>
                    </div>

                    <form @submit.prevent="saveDelegation">
                        <div class="row g-3 mb-4 bg-light p-3 rounded border">
                            <div class="col-12 col-md-12 mb-2">
                                <label class="form-label small fw-bold text-secondary">Delegate Approvals To</label>
                                <select v-model="delegationForm.delegated_to_id" class="form-select border-0 shadow-sm">
                                    <option value="">-- No Active Delegation --</option>
                                    <option v-for="c in filteredColleagues" :key="getSafeId(c)" :value="getSafeId(c)">
                                        {{ c.name }} ({{ c.department }})
                                    </option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-secondary">Start Date</label>
                                <input type="date" v-model="delegationForm.delegation_start" class="form-control border-0 shadow-sm" :required="delegationForm.delegated_to_id !== ''">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-secondary">End Date</label>
                                <input type="date" v-model="delegationForm.delegation_end" class="form-control border-0 shadow-sm" :required="delegationForm.delegated_to_id !== ''">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-dark px-4" :disabled="isSavingDelegation">
                                <span v-if="isSavingDelegation" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Update Delegation Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm bg-primary text-white p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-circle-info me-2"></i>How Delegation Works</h6>
                    <p class="small text-white text-opacity-75 mb-3">When you set an active delegation period, any new purchase requests created by your department will automatically be routed to the manager you select here.</p>
                    <p class="small text-white text-opacity-75 mb-0">Once the end date passes, approval routing will automatically revert back to you. You can cancel delegation at any time by selecting "No Active Delegation".</p>
                </div>
            </div>
        </div>
    </MainLayout>
</template>