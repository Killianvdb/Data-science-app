<x-app-layout :title="'Admin Page - Users'">

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin Panel - Users') }}
        </h2>
    </x-slot>



    <div class="py-6">

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif


            @if(session('error'))
                <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif



        <div x-data="userTable()" x-init="init()" class="space-y-4">


            <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-4">
                <input type="text" x-model="search" @input.debounce.300ms="page = 1; fetchUsers()"
                    placeholder="Search by ID, name or email..."
                    class="w-full sm:w-auto flex-1 border rounded px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400">

                <div class="flex gap-2 mt-2 sm:mt-0">
                    <button @click="roleFilter=''; page = 1; fetchUsers()"
                        :class="roleFilter===''?'bg-blue-500 text-white':'bg-gray-200 text-gray-800'"
                        class="px-3 py-1 rounded">All</button>
                    <button @click="roleFilter='admin'; page = 1; fetchUsers()"
                        :class="roleFilter==='admin'?'bg-blue-500 text-white':'bg-gray-200 text-gray-800'"
                        class="px-3 py-1 rounded">Admins</button>
                    <button @click="roleFilter='user'; page = 1; fetchUsers()"
                        :class="roleFilter==='user'?'bg-blue-500 text-white':'bg-gray-200 text-gray-800'"
                        class="px-3 py-1 rounded">Users</button>
                </div>
            </div>

            <div class="bg-white shadow-md rounded overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th @click="sort('id')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th @click="sort('name')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th @click="sort('email')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th @click="sort('role')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="user in users" :key="user.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap" x-text="user.id"></td>
                                <td class="px-6 py-4 whitespace-nowrap" x-text="user.name"></td>
                                <td class="px-6 py-4 whitespace-nowrap" x-text="user.email"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <template x-if="user.id !== 1 && user.id !== {{ auth()->id() }}">
                                        <select x-model="user.role" @change="toggleRole(user)"
                                                class="border rounded-lg px-7 py-1 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                                            <option value="user">user</option>
                                            <option value="admin">admin</option>
                                        </select>
                                    </template>
                                    <template x-if="user.id === 1 || user.id === {{ auth()->id() }}">
                                        <span x-text="user.role"></span>
                                    </template>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <template x-if="user.id !== 1">
                                        <button @click="deleteUser(user)"
                                                class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">Delete</button>
                                    </template>
                                    <template x-if="user.id === 1">
                                        <span class="text-gray-400 px-3 py-1 rounded">Delete</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>


            <div class="flex justify-between mt-4">
                <button :disabled="page === 1" @click="page--; fetchUsers()"
                    class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Previous</button>

                <span>Page <span x-text="page"></span> of <span x-text="lastPage"></span></span>

                <button :disabled="page === lastPage" @click="page++; fetchUsers()"
                    class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
            </div>



        </div>


        </div>
    </div>

    <script>
    function userTable() {
        return {
            users: [],
            search: '',
            roleFilter: '',
            sortField: 'id',
            sortAsc: true,
            page: 1,
            lastPage: 1,

            async init() {
                await this.fetchUsers();
            },

            async fetchUsers() {
                let params = new URLSearchParams({
                    query: this.search,
                    role: this.roleFilter,
                    sort: this.sortField,
                    direction: this.sortAsc ? 'asc' : 'desc',
                    page: this.page
                });
                let res = await fetch(`/admin/users/search?${params.toString()}`);
                let data = await res.json();
                this.users = data.data;

                this.page = data.current_page;
                this.lastPage = data.last_page;

            },

            async sort(field) {
                if(this.sortField === field){
                    this.sortAsc = !this.sortAsc;
                } else {
                    this.sortField = field;
                    this.sortAsc = true;
                }
                await this.fetchUsers();
            },

            async toggleRole(user){
                if(user.id===1) return;

                try{
                    let res = await fetch(`/admin/users/${user.id}/role`,{
                        method:'PATCH',
                        headers:{
                            'Content-Type':'application/json',
                            'X-CSRF-TOKEN':'{{ csrf_token() }}'
                        },
                        body: JSON.stringify({role:user.role})
                    });

                    let data = await res.json();

                    if(data.success){
                        user.role = data.role;
                        alert(data.success);
                    } else {
                        alert(data.error || 'Error updating role');
                        await this.fetchUsers();
                    }

                }catch(e){
                    alert('Error updating role');
                    await this.fetchUsers();
                }
            },

            async deleteUser(user){
                if(user.id===1 || user.id==={{ auth()->id() }}) return;
                if(!confirm(`Are you sure you want to delete ${user.name}?`)) return;
                await fetch(`/admin/users/${user.id}`,{
                    method:'DELETE',
                    headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}
                });
                await this.fetchUsers();
            }
        }
    }
    </script>

</x-app-layout>
