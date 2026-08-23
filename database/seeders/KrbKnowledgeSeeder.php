<?php

namespace Database\Seeders;

use App\Models\KrbKnowledgeDocument;
use App\Models\KrbKnowledgeSource;
use Illuminate\Database\Seeder;

class KrbKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $officialSource = KrbKnowledgeSource::updateOrCreate(
            ['name' => 'Kebun Raya Bogor Official Knowledge Base'],
            [
                'type'             => 'curated_dataset',
                'trust_level'      => 'verified',
                'approval_status'  => 'approved',
                'source_reference' => 'BRIN Directorate of Plant Conservation and Botanic Gardens / KRB Official Compendium',
                'publication_date' => '2024-01-01',
                'verified_at'      => now(),
                'is_active'        => true,
            ]
        );

        $documents = [
            [
                'category' => 'history',
                'title'    => 'History and Founding of Kebun Raya Bogor',
                'slug'     => 'history-and-founding',
                'summary'  => 'Kebun Raya Bogor was founded on May 18, 1817 by Caspar Georg Carl Reinwardt.',
                'content'  => "Kebun Raya Bogor (Bogor Botanical Garden) was officially established on May 18, 1817, under the name 's Lands Plantentuin te Buitenzorg by Caspar Georg Carl (C.G.C.) Reinwardt, a German-born Dutch botanist. It covers approximately 87 hectares in the heart of Bogor, West Java, Indonesia, adjacent to the Bogor Presidential Palace (Istana Bogor). It is the oldest botanical garden in Southeast Asia and served as the epicenter of botanical exploration, agricultural research, and plant acclimatization in the region, including early trials of oil palm, tea, and cinchona (quinine).",
                'keywords' => ['sejarah', 'history', 'founder', 'pendiri', 'reinwardt', 'caspar georg carl', '1817', '18 mei 1817', 'lands plantentuin', 'buitenzorg', 'luas', '87 hektar', 'istana bogor'],
                'metadata' => ['year_founded' => 1817, 'founder' => 'C.G.C. Reinwardt', 'area_hectares' => 87],
            ],
            [
                'category' => 'collections',
                'title'    => 'Living Collections and Plant Statistics',
                'slug'     => 'living-collections-statistics',
                'summary'  => 'Kebun Raya Bogor houses over 12,000 plant specimens representing more than 3,000 species.',
                'content'  => "Kebun Raya Bogor maintains over 12,000 living plant specimens representing more than 3,000 species and 200 plant families. Key living collections include orchids (Orchidaceae), palms (Arecaceae), bamboo (Bambusoideae), medicinal plants (Taman Obat & Rempah), aquatic plants (Taman Akuatik), and succulents/cacti (Taman Meksiko). Each living plant in the garden is cataloged and tagged with botanical registration numbers (sub-block and accession tags) for research and conservation monitoring.",
                'keywords' => ['koleksi', 'collections', 'jumlah tanaman', 'plant count', 'spesies', 'species', '12000', '3000', 'palem', 'anggrek', 'bambu', 'taman obat', 'tanaman obat', 'herbarium'],
                'metadata' => ['total_specimens' => 12000, 'total_species' => 3000],
            ],
            [
                'category' => 'botany',
                'title'    => 'Iconic Flora: Amorphophallus titanum and Rafflesia patma',
                'slug'     => 'iconic-flora-titanum-rafflesia',
                'summary'  => 'Notable botanical icons at Kebun Raya Bogor include the giant Titan Arum and parasitic Rafflesia patma.',
                'content'  => "Kebun Raya Bogor is famous for cultivating iconic and rare flora:
1. Amorphophallus titanum (Titan Arum / Bunga Bangkai): Native to Sumatra, famous for producing one of the world's largest unbranched inflorescences that can exceed 2 to 3 meters in height and emits a carrion odor to attract pollinators during its brief 24-48 hour blooming period.
2. Rafflesia patma: A rare, holoparasitic plant native to Java and Sumatra. Kebun Raya Bogor made botanical history by successfully cultivating and flowering Rafflesia patma ex-situ on its host vine Tetrastigma.
3. Victoria amazonica (Giant Amazon Water Lily): Located in aquatic ponds with massive floating leaves capable of reaching up to 2-3 meters in diameter.",
                'keywords' => ['rafflesia', 'rafflesia patma', 'amorphophallus titanum', 'bunga bangkai', 'titan arum', 'victoria amazonica', 'teratai raksasa', 'bunga langka', 'flora langka'],
                'metadata' => ['iconic_species' => ['Amorphophallus titanum', 'Rafflesia patma', 'Victoria amazonica']],
            ],
            [
                'category' => 'collections',
                'title'    => 'Oil Palm Historical Heritage (Elaeis guineensis)',
                'slug'     => 'oil-palm-historical-heritage',
                'summary'  => 'The origins of the Southeast Asian oil palm industry trace back to 4 seedlings planted in Kebun Raya Bogor in 1848.',
                'content'  => "In 1848, four oil palm seedlings (Elaeis guineensis) from West Africa and Amsterdam botanical gardens were planted in Kebun Raya Bogor. These four parent trees thrived in Bogor and became the direct genetic ancestry of commercial oil palm plantations across Indonesia and Malaysia, fundamentally shaping the agricultural economy of Southeast Asia.",
                'keywords' => ['kelapa sawit', 'oil palm', 'elaeis guineensis', '1848', 'sejarah sawit', 'pohon sawit pertama', 'induk kelapa sawit'],
                'metadata' => ['introduction_year' => 1848, 'origin' => 'West Africa / Amsterdam'],
            ],
            [
                'category' => 'landmarks',
                'title'    => 'Key Landmarks: Griya Anggrek, Danau Gunting, and Jembatan Merah',
                'slug'     => 'key-landmarks',
                'summary'  => 'Famous historical and architectural landmarks inside Kebun Raya Bogor.',
                'content'  => "Major landmarks and points of interest inside Kebun Raya Bogor include:
- Griya Anggrek (Orchid House): Specialized greenhouse facilities displaying hundreds of hybrid and wild natural Indonesian orchid species.
- Danau Gunting (Gunting Lake): A scenic lake near the Istana Bogor courtyard, famous for its lotus flowers and water birds.
- Jembatan Merah / Jembatan Gantung (Red Suspension Bridge): An iconic pedestrian suspension bridge crossing the Ciliwung river branch inside the gardens.
- Monumen Lady Raffles: A classical romantic gazebo monument dedicated to Olivia Mariamne Raffles, wife of Thomas Stamford Raffles, who died in 1814.
- Taman Meksiko (Mexican Garden): Houses an extensive outdoor collection of over 100 species of desert succulents, agaves, and cacti.
- Makam Belanda Kuno (Old Dutch Cemetery): A peaceful historical cemetery with 42 graves predating the official garden founding, dating from 1784 to the early 20th century.
- Astrid Avenue (Jalan Astrid): A wide dual avenue lined with colorful Canna flowers, named in commemoration of the visit by Crown Princess Astrid of Belgium in 1928.",
                'keywords' => ['landmark', 'griya anggrek', 'danau gunting', 'jembatan merah', 'jembatan gantung', 'monumen lady raffles', 'olivia raffles', 'taman meksiko', 'makam belanda', 'astrid avenue', 'jalan astrid', 'spot menarik'],
                'metadata' => ['notable_landmarks' => ['Griya Anggrek', 'Danau Gunting', 'Jembatan Merah', 'Monumen Lady Raffles', 'Taman Meksiko', 'Makam Belanda']],
            ],
            [
                'category' => 'conservation',
                'title'    => 'Conservation, Research, and Management under BRIN',
                'slug'     => 'conservation-research-brin',
                'summary'  => 'Kebun Raya Bogor functions under BRIN for ex-situ plant conservation and botanical research.',
                'content'  => "Kebun Raya Bogor is managed under the National Research and Innovation Agency (BRIN — Badan Riset dan Inovasi Nasional), specifically the Directorate of Plant Conservation and Botanic Gardens. Its core five functions (Panca Dharma Kebun Raya) are:
1. Ex-situ Conservation: Preserving endangered, rare, and economically important plant taxa outside their natural habitats.
2. Scientific Research: Taxonomy, biosystematics, horticulture, ecological restoration, seed biology, and phytochemistry.
3. Environmental Education: Raising public ecological awareness and hosting educational study programs.
4. Tourism & Recreation: Providing sustainable nature-based public recreation and green open space.
5. Ecosystem Services: Acting as an urban carbon sink, watershed filter, and microclimate regulator for Bogor city.",
                'keywords' => ['brin', 'konservasi', 'conservation', 'penelitian', 'research', 'panca dharma', 'ex-situ', 'fungsi kebun raya', 'tugas kebun raya'],
                'metadata' => ['governing_body' => 'BRIN', 'pillars' => 'Panca Dharma'],
            ],
            [
                'category' => 'facilities',
                'title'    => 'Visitor Facilities, Operating Hours, and Services',
                'slug'     => 'visitor-facilities-services',
                'summary'  => 'Visitor guidelines, opening hours, rental transport, and visitor facilities.',
                'content'  => "Visitor information for Kebun Raya Bogor:
- Operating Hours: Open daily from 08:00 to 16:00 WIB on weekdays (Monday–Friday) and 07:00 to 16:00/17:00 WIB on weekends and public holidays.
- Internal Transport: Electric shuttle buses, golf cart rentals, and bicycles are available for visitors to explore the 87-hectare grounds comfortably.
- Educational Facilities: Visitor Center, Museum Zoologi (Zoological Museum featuring fauna specimens and a giant blue whale skeleton), Herbarium Bogoriense, and Ecodome.
- Amenities: Cafes (including Resto Raasaa), prayer rooms (musholla), accessible pathways, benches, and clean restroom facilities distributed throughout the garden zones.",
                'keywords' => ['jam buka', 'operating hours', 'fasilitas', 'facilities', 'shuttle bus', 'golf cart', 'sewa sepeda', 'museum zoologi', 'ecodome', 'resto raasaa', 'visitor center', 'jam operasional', 'tiket'],
                'metadata' => ['regular_hours' => '08:00-16:00 WIB', 'weekend_hours' => '07:00-17:00 WIB'],
            ],
        ];

        foreach ($documents as $doc) {
            KrbKnowledgeDocument::updateOrCreate(
                ['slug' => $doc['slug']],
                [
                    'source_id' => $officialSource->source_id,
                    'category'  => $doc['category'],
                    'title'     => $doc['title'],
                    'summary'   => $doc['summary'],
                    'content'   => $doc['content'],
                    'keywords'  => $doc['keywords'],
                    'metadata'  => $doc['metadata'],
                    'is_active' => true,
                ]
            );
        }
    }
}

