@extends('layouts.app')

@section('title', 'Assign Service Request - UrbanServe')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Assign Service Request</h1>
        </div>

        <!-- Pending Service Requests -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Pending Service Requests</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Request ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Service Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Client Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Preferred Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">SR12345</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Plumbing Repair</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">123 Elm Street, Anytown</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Tomorrow, 2 PM</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Pending
                                </span>
                            </td>
                        </tr>

                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">SR67890</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">House Cleaning</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">456 Oak Avenue, Anytown</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Next Week, 10 AM</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Pending
                                </span>
                            </td>
                        </tr>

                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">SR24680</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Electrical Installation</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">789 Pine Lane, Anytown</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Today, 4 PM</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Pending
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Available Partners -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Available Partners</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Partner ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Skills</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Availability</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">P1001</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Alex Johnson</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Plumbing, Electrical</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Anytown</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Available
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                                    Assign
                                </button>
                            </td>
                        </tr>

                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">P1002</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Maria Rodriguez</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">House Cleaning</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Anytown</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Available
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                                    Assign
                                </button>
                            </td>
                        </tr>

                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">P1003</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">David Lee</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Electrical</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Anytown</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Unavailable
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                                    Assign
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
