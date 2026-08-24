<?php

namespace Database\Seeders;

use App\Models\DigitalResource;
use Illuminate\Database\Seeder;

class DigitalLibraryStarterSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            ['ncert-ebooks', 'NCERT eBooks (Classes I–XII)', 'ebook', 'School & College', 'Official NCERT textbooks, flipbooks and EPUB resources for school students.', 'https://ncert.nic.in/ebooks.php'],
            ['epathshala-learning-resources', 'ePathshala Learning Resources', 'ebook', 'School & College', 'Official NCERT eBooks, audio, video and other school learning resources.', 'https://epathshala.nic.in/'],
            ['swayam-online-courses', 'SWAYAM Online Courses', 'video', 'School & College', 'Government of India online courses for school, undergraduate and postgraduate learners.', 'https://swayam.gov.in/'],
            ['egyankosh-open-learning', 'eGyanKosh Open Learning Repository', 'ebook', 'School & College', 'IGNOU digital learning material and open educational resources.', 'https://egyankosh.ac.in/'],
            ['upsc-previous-papers', 'UPSC Previous Question Papers', 'question_paper', 'Competitive Exams', 'Official previous-year papers for Civil Services, CDS, NDA and other UPSC examinations.', 'https://www.upsc.gov.in/examinations/previous-question-papers'],
            ['bpsc-official-portal', 'BPSC Official Exam Resources', 'link', 'Competitive Exams', 'Official Bihar Public Service Commission notices, syllabus and examination resources.', 'https://bpsc.bihar.gov.in/'],
            ['ssc-official-portal', 'SSC Official Exam Portal', 'link', 'Competitive Exams', 'Official SSC notices, syllabus, examination calendar and candidate resources.', 'https://ssc.gov.in/'],
            ['railway-recruitment-board', 'Railway Recruitment Board Resources', 'link', 'Competitive Exams', 'Official Railway Recruitment Board notices and examination information.', 'https://www.rrbcdg.gov.in/'],
            ['ibps-banking-exams', 'IBPS Banking Exam Resources', 'link', 'Competitive Exams', 'Official banking recruitment notifications and examination information from IBPS.', 'https://www.ibps.in/'],
            ['nta-exam-resources', 'NTA Examination Resources', 'link', 'Competitive Exams', 'Official information for national entrance and eligibility examinations conducted by NTA.', 'https://www.nta.ac.in/'],
            ['diksha-teacher-learning', 'DIKSHA Teacher & Student Learning', 'video', 'Competitive Exams', 'Government learning platform for teachers and school students with courses and practice material.', 'https://diksha.gov.in/'],
            ['spoken-tutorial-computer-courses', 'Spoken Tutorial Computer Courses', 'video', 'Computer Education', 'IIT Bombay tutorials for programming, office applications and open-source software.', 'https://spoken-tutorial.org/tutorial-search/'],
            ['nptel-computer-science', 'NPTEL Computer Science Courses', 'video', 'Computer Education', 'Free IIT and IISc video courses in programming, computer science and related subjects.', 'https://nptel.ac.in/courses'],
            ['python-official-tutorial', 'Python Official Tutorial', 'notes', 'Computer Education', 'Official Python documentation for learning programming fundamentals and practical coding.', 'https://docs.python.org/3/tutorial/'],
            ['swayam-prabha-education', 'SWAYAM PRABHA Educational Channels', 'video', 'Language & Skills', 'Government educational video channels covering communication, languages and academic skills.', 'https://www.swayamprabha.gov.in/'],
            ['national-career-service', 'National Career Service', 'link', 'Career Materials', 'Official career guidance, job information, counselling and skill-development services.', 'https://www.ncs.gov.in/'],
            ['employment-news', 'Employment News / Rozgar Samachar', 'link', 'Career Materials', 'Official weekly information on government jobs, careers and recruitment opportunities.', 'https://employmentnews.gov.in/'],
            ['upsc-examination-notices', 'UPSC Examination Notices & Syllabus', 'notes', 'Syllabus & Exam Pattern', 'Official examination notices, rules, syllabus and exam-pattern information.', 'https://www.upsc.gov.in/examinations/exam-notifications'],
            ['national-digital-library', 'National Digital Library of India', 'ebook', 'Notes & eBooks', 'A national portal for books, articles, lectures and learning resources across subjects.', 'https://ndl.iitkgp.ac.in/'],
            ['pib-current-affairs', 'Press Information Bureau Updates', 'link', 'Current Affairs', 'Official Government of India releases useful for current-affairs preparation.', 'https://pib.gov.in/'],
            ['newsonair-current-affairs', 'News On AIR', 'link', 'Current Affairs', 'Prasar Bharati news and public-information updates for current-affairs study.', 'https://www.newsonair.gov.in/'],
            ['cec-ugc-educational-videos', 'CEC-UGC Educational Videos', 'video', 'Video Lectures', 'Higher-education video lectures and academic programmes from the Consortium for Educational Communication.', 'https://cec.nic.in/'],
        ];

        foreach ($resources as [$key, $title, $type, $category, $description, $url]) {
            DigitalResource::updateOrCreate(
                ['slug' => 'starter-'.$key],
                [
                    'branch_id' => null,
                    'title' => $title,
                    'resource_type' => $type,
                    'category' => $category,
                    'description' => $description,
                    'file_path' => null,
                    'external_url' => $url,
                    'access_type' => 'public',
                    'download_allowed' => false,
                    'status' => true,
                    'uploaded_by' => null,
                ]
            );
        }

        $this->command?->info(count($resources).' official/open Digital Library starter resources created or refreshed.');
    }
}
