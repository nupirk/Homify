<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServicePricing;
use Illuminate\Database\Seeder;

/**
 * The minimum catalog the mobile app needs to be usable: service categories,
 * their services and one active "standard" price per service.
 *
 * Idempotent - keyed on name, so re-running updates copy/prices in place
 * instead of duplicating rows. Prices are NPR.
 */
class CoreCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'category' => [
                    'name' => 'Home Cleaning',
                    'description' => 'Professional home cleaning services',
                    'icon_url' => 'https://images.unsplash.com/photo-1563453392212-326f5e854473?w=64&h=64&fit=crop',
                    'image_url' => 'https://images.unsplash.com/photo-1563453392212-326f5e854473?w=400&h=250&fit=crop',
                    'sort_order' => 1,
                ],
                'services' => [
                    [
                        'name' => 'Deep House Cleaning',
                        'description' => 'Complete deep cleaning of the whole house including all rooms, bathrooms and kitchen.',
                        'image_url' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=400&h=300&fit=crop',
                        'features' => [
                            'All rooms cleaned',
                            'Bathroom sanitization',
                            'Kitchen deep clean',
                            'Window cleaning',
                            'Floor mopping and vacuuming',
                        ],
                        'terms_conditions' => 'Service includes basic cleaning supplies. Customer should provide access to all areas.',
                        'base_price' => 4500.00,
                        'estimated_duration_min' => 240,
                        'is_featured' => true,
                        'is_popular' => false,
                        'sort_order' => 1,
                    ],
                    [
                        'name' => 'Regular House Cleaning',
                        'description' => 'Regular maintenance cleaning to keep the home fresh and tidy.',
                        'image_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=300&fit=crop',
                        'features' => [
                            'Living areas dusting',
                            'Bathroom cleaning',
                            'Kitchen cleaning',
                            'Trash removal',
                            'Basic floor cleaning',
                        ],
                        'terms_conditions' => 'Regular service recommended weekly or bi-weekly.',
                        'base_price' => 1500.00,
                        'estimated_duration_min' => 120,
                        'is_featured' => false,
                        'is_popular' => true,
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'category' => [
                    'name' => 'Home Repair',
                    'description' => 'Home maintenance and repair services',
                    'icon_url' => 'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?w=64&h=64&fit=crop',
                    'image_url' => 'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?w=400&h=250&fit=crop',
                    'sort_order' => 2,
                ],
                'services' => [
                    [
                        'name' => 'Plumbing Repair',
                        'description' => 'Fix leaks, unclog drains and handle basic plumbing issues.',
                        'image_url' => 'https://images.unsplash.com/photo-1585704032915-c3400ca199e7?w=400&h=300&fit=crop',
                        'features' => [
                            'Leak detection and repair',
                            'Drain unclogging',
                            'Faucet installation',
                            'Pipe repair',
                            'Emergency service available',
                        ],
                        'terms_conditions' => 'Parts and materials charged separately. Emergency calls have additional fees.',
                        'base_price' => 800.00,
                        'estimated_duration_min' => 60,
                        'is_featured' => false,
                        'is_popular' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'name' => 'Electrical Repair',
                        'description' => 'Safe and professional electrical repairs and installations.',
                        'image_url' => 'https://images.unsplash.com/photo-1621905252507-b35492cc74b4?w=400&h=300&fit=crop',
                        'features' => [
                            'Outlet installation',
                            'Light fixture repair',
                            'Circuit breaker issues',
                            'Wiring repair',
                            'Safety inspection',
                        ],
                        'terms_conditions' => 'Licensed electrician will handle all work. Permits may be required for major work.',
                        'base_price' => 900.00,
                        'estimated_duration_min' => 60,
                        'is_featured' => false,
                        'is_popular' => false,
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'category' => [
                    'name' => 'Gardening',
                    'description' => 'Garden maintenance and landscaping',
                    'icon_url' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=64&h=64&fit=crop',
                    'image_url' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&h=250&fit=crop',
                    'sort_order' => 3,
                ],
                'services' => [
                    [
                        'name' => 'Lawn Mowing',
                        'description' => 'Professional lawn mowing and basic yard maintenance.',
                        'image_url' => 'https://images.unsplash.com/photo-1558904541-efa843a96f01?w=400&h=300&fit=crop',
                        'features' => [
                            'Grass cutting',
                            'Edge trimming',
                            'Leaf blowing',
                            'Basic weed removal',
                            'Grass collection and disposal',
                        ],
                        'terms_conditions' => 'Customer should mark any obstacles or special areas to avoid.',
                        'base_price' => 700.00,
                        'estimated_duration_min' => 60,
                        'is_featured' => false,
                        'is_popular' => false,
                        'sort_order' => 1,
                    ],
                    [
                        'name' => 'Garden Maintenance',
                        'description' => 'Complete garden care including pruning, weeding and plant care.',
                        'image_url' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&h=300&fit=crop',
                        'features' => [
                            'Plant pruning',
                            'Weed removal',
                            'Fertilizing',
                            'Watering system check',
                            'Seasonal plant care',
                        ],
                        'terms_conditions' => 'Seasonal services available. Plant replacement not included unless specified.',
                        'base_price' => 1200.00,
                        'estimated_duration_min' => 90,
                        'is_featured' => false,
                        'is_popular' => false,
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'category' => [
                    'name' => 'Beauty & Wellness',
                    'description' => 'Personal care and beauty services',
                    'icon_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=64&h=64&fit=crop',
                    'image_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=400&h=250&fit=crop',
                    'sort_order' => 4,
                ],
                'services' => [
                    [
                        'name' => 'Hair Cut & Styling',
                        'description' => 'Professional haircuts and styling for all hair types.',
                        'image_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=400&h=300&fit=crop',
                        'features' => [
                            'Consultation included',
                            'Wash and cut',
                            'Professional styling',
                            'Hair treatment',
                            'Styling tips',
                        ],
                        'terms_conditions' => 'Appointments required. Cancellation 24 hours in advance.',
                        'base_price' => 600.00,
                        'estimated_duration_min' => 60,
                        'is_featured' => false,
                        'is_popular' => false,
                        'sort_order' => 1,
                    ],
                    [
                        'name' => 'Manicure & Pedicure',
                        'description' => 'Complete nail care service for hands and feet.',
                        'image_url' => 'https://images.unsplash.com/photo-1607779097040-26e80aa78e66?w=400&h=300&fit=crop',
                        'features' => [
                            'Nail trimming and shaping',
                            'Cuticle care',
                            'Polish application',
                            'Hand and foot massage',
                            'Moisturizing treatment',
                        ],
                        'terms_conditions' => 'Please bring your own polish if you have a preferred color.',
                        'base_price' => 1200.00,
                        'estimated_duration_min' => 90,
                        'is_featured' => false,
                        'is_popular' => false,
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'category' => [
                    'name' => 'Tech Support',
                    'description' => 'Technology and electronics services',
                    'icon_url' => 'https://images.unsplash.com/photo-1581092795360-fd1ca04f0952?w=64&h=64&fit=crop',
                    'image_url' => 'https://images.unsplash.com/photo-1581092795360-fd1ca04f0952?w=400&h=250&fit=crop',
                    'sort_order' => 5,
                ],
                'services' => [
                    [
                        'name' => 'Computer Repair',
                        'description' => 'Diagnosis and repair of desktop and laptop computers.',
                        'image_url' => 'https://images.unsplash.com/photo-1581092795360-fd1ca04f0952?w=400&h=300&fit=crop',
                        'features' => [
                            'Hardware diagnosis',
                            'Software troubleshooting',
                            'Virus removal',
                            'Data recovery',
                            'Performance optimization',
                        ],
                        'terms_conditions' => 'Data backup recommended before service. Parts charged separately.',
                        'base_price' => 1500.00,
                        'estimated_duration_min' => 90,
                        'is_featured' => false,
                        'is_popular' => false,
                        'sort_order' => 1,
                    ],
                    [
                        'name' => 'Smart Home Setup',
                        'description' => 'Installation and configuration of smart home devices.',
                        'image_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=300&fit=crop',
                        'features' => [
                            'Device installation',
                            'Network configuration',
                            'App setup',
                            'Training included',
                            'Troubleshooting support',
                        ],
                        'terms_conditions' => 'Compatible devices required. WiFi network must be available.',
                        'base_price' => 2500.00,
                        'estimated_duration_min' => 120,
                        'is_featured' => false,
                        'is_popular' => false,
                        'sort_order' => 2,
                    ],
                ],
            ],
        ];

        $serviceCount = 0;
        $pricingCount = 0;

        foreach ($catalog as $group) {
            $category = Category::updateOrCreate(
                ['name' => $group['category']['name']],
                $group['category'] + ['is_active' => true]
            );

            foreach ($group['services'] as $serviceData) {
                $pricingAttributes = [
                    'base_price' => $serviceData['base_price'],
                    'estimated_duration_min' => $serviceData['estimated_duration_min'],
                    'unit' => 'fixed',
                    'description' => 'Standard service package',
                    'is_active' => true,
                ];

                unset(
                    $serviceData['base_price'],
                    $serviceData['estimated_duration_min'],
                );

                $service = Service::updateOrCreate(
                    ['name' => $serviceData['name']],
                    $serviceData + [
                        'category_id' => $category->category_id,
                        'is_active' => true,
                    ]
                );
                $serviceCount++;

                ServicePricing::updateOrCreate(
                    ['service_id' => $service->service_id, 'tier_name' => 'standard'],
                    $pricingAttributes
                );
                $pricingCount++;
            }
        }

        $this->command->info("Catalog ready: {$serviceCount} services, {$pricingCount} prices.");
    }
}
