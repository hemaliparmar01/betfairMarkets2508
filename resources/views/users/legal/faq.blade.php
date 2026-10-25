@extends('users.layouts.app', ['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('home.en'), 'moneywayUrl' => route('football.moneyway.page')])


@php
    $faqs = [
        'ka' => [
            [
                'question' => 'რა არის BF Markets?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>BF Markets</strong> არის სპორტული ბირჟის მონაცემთა ანალიტიკური პლატფორმა, რომელიც მომხმარებლებს აწვდის:</p>
                        <ul class="my-2 pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:content-['✅']">
                            <li> რეალურ დროში კოეფიციენტებს</li>
                            <li> ლიკვიდობის ანალიზს</li>
                            <li> ბაზრის სიღრმის ვიზუალიზაციას</li>
                            <li> დინამიკურ ჩარტებს</li>
                            <li> ჭკვიან ფილტრებს</li>
                            <li> ბაზრებს შორის კორელაციების მონიტორინგს</li>
                            <li> მყისიერ შეტყობინებებსა და ალერტებს</li>
                        </ul>
                        <p class="mt-2 text-[rgb(var(--warning))] bg-[rgb(var(--warning)_/_0.10)] p-3 rounded-lg border border-[rgb(var(--warning)_/_0.25)] text-sm">⚠️ BF Markets არ არის ბუკმეიკერი, არ იღებს ფსონებს და არ ახორციელებს აზარტულ საქმიანობას.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'რა არის სპორტული ბირჟის ანალიტიკა?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>სპორტული ბირჟის ანალიტიკა არის რეალურ დროში მონაცემების მონიტორინგისა და ანალიზის პროცესი, რომელიც მოიცავს:</p>
                        <ul>
                            <li>კოეფიციენტების მოძრაობას;</li>
                            <li>ლიკვიდობის დონეებს;</li>
                            <li>ბაზრის სიღრმეს;</li>
                            <li>სხვადასხვა ბაზრებს შორის კორელაციებს;</li>
                            <li>ფასების დინამიკასა და მიმართულებას.</li>
                        </ul>
                        <p class="mt-3"><strong>BF Markets-ის მთავარი უპირატესობა</strong> ის არის, რომ მომხმარებელს შეუძლია ერთი ეკრანიდან, სხვადასხვა გვერდებზე მუდმივი გადასვლის, ხანგრძლივი ძებნისა და მნიშვნელოვანი შესაძლებლობების გამორჩენის გარეშე, მაქსიმალურად სწრაფად მიიღოს სრული სურათი სპორტული ბირჟის ბაზრების შესახებ.</p>
                        <p class="mt-2">პლატფორმა საშუალებას გაძლევთ:</p>
                        <ul>
                            <li>✓ რეალურ დროში დაინახოთ, სად მიედინება თანხები;</li>
                            <li>✓ აღმოაჩინოთ, რომელი ბაზრებია ყველაზე ლიკვიდური;</li>
                            <li>✓ აკონტროლოთ კოეფიციენტების ზრდა და კლება;</li>
                            <li>✓ შეაფასოთ ბაზრის დინამიკა და მიმართულება;</li>
                            <li>✓ ერთდროულად რამდენიმე სპორტისა და ბაზრის მოძრაობას მიადევნოთ თვალი;</li>
                            <li>✓ სწრაფად აღმოაჩინოთ მნიშვნელოვანი ცვლილებები და ბაზარზე წარმოქმნილი შესაძლებლობები.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'რა არის საბეთინგო ბირჟის მონაცემთა ტერმინალი?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>ეს არის პროფესიონალური პროგრამული პლატფორმა, რომელიც უზრუნველყოფს:</p>
                        <ul>
                            <li>რეალურ დროში კოეფიციენტებს;</li>
                            <li>ლიკვიდობის მონაცემებს;</li>
                            <li>ბაზრის სიღრმეს;</li>
                            <li>ანალიტიკურ ინსტრუმენტებს.</li>
                        </ul>
                        <p class="mt-2"><strong>BF Markets</strong> ხშირად მოიხსენიება, როგორც <em>„Bloomberg სპორტული ბირჟებისთვის“</em>.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'როგორ მუშაობს ლიკვიდობის ტრეკინგი?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>ლიკვიდობის ტრეკინგი აკვირდება თანხის მოცულობას, რომელიც ხელმისაწვდომია სხვადასხვა კოეფიციენტების დონეებზე.</p>
                        <p class="mt-2">ჩვენი პლატფორმა ამას ვიზუალიზაციას უკეთებს:</p>
                        <ul>
                            <li>✓ Heatmap-ებით;</li>
                            <li>✓ Order Book-ის სიღრმის დიაგრამებით;</li>
                            <li>✓ ბაზრის დინამიკის ანალიზით.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'რა ბაზრებს ფარავს BF Markets?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>ჩვენ ვფარავთ მრავალ საბირჟო ბაზარს, მათ შორის:</p>
                        <ul>
                            <li>✓ Match Odds;</li>
                            <li>✓ Over/Under;</li>
                            <li>✓ Correct Score;</li>
                            <li>✓ Double Chance;</li>
                            <li>✓ Asian Handicap;</li>
                            <li>✓ სპეციალიზებულ საბირჟო ბაზრებს.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'რომელი სპორტის სახეობებია ხელმისაწვდომი?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>BF Markets ფარავს ყველა სპორტის სახეობას, რომელსაც მხარს უჭერს გლობალური სპორტული ბირჟა Betfair.</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">⚽ Football</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🎾 Tennis</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏀 Basketball</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏏 Cricket</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏈 American Football</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🎮 eSport</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🎯 Darts</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🎱 Snooker</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏒 Ice Hockey</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏉 Rugby</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🥊 Boxing</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">⚾ Baseball</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏐 Volleyball</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🤾 Handball</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🥋 MMA</span>
                        </div>
                        <p class="mt-2 text-sm text-[rgb(var(--text-muted))]">და სხვა დამატებითი სპორტები.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'გაქვთ ცხენების რბოლები და მათი ბაზრების ანალიზი?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>დიახ. BF Markets უკვე გთავაზობთ ცხენების რბოლების ყოველდღიურ ანალიზს, რომელიც ყოველდღიურად ახლდება და მოიცავს:</p>
                        <ul>
                            <li>რბოლების დეტალურ ანალიზს და ფავორიტების შერჩევას</li>
                            <li>ექსპერტთა კონსენსუსსა და ბაზრის რეიტინგებს</li>
                            <li>ძირითადი მონაწილეების შეფასებას და ტაქტიკურ მიმოხილვებს</li>
                        </ul>
                        <p>ამჟამად მიმდინარეობს Betfair-ის ბაზრების ჩარტებისა და კოეფიციენტების ცვლილებების რეალურ დროში მონიტორინგის მოდულის განვითარება.</p>
                        <p class="mt-2">როგორც კი ეს ფუნქციები მზად იქნება, ისინი ავტომატურად დაემატება ცხენების რბოლების განყოფილებას და მომხმარებლებს სრულყოფილ ბაზრის ანალიტიკას შესთავაზებს.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'რა არის Smart Filters?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>Smart Filters საშუალებას გაძლევთ რამდენიმე წამში იპოვოთ:</p>
                        <ul>
                            <li>✓ Fav Losing;</li>
                            <li>✓ HT 0-0;</li>
                            <li>✓ 0-0 @ 70';</li>
                            <li>✓ Prematch Drops;</li>
                            <li>✓ Market Shock Detector;</li>
                            <li>✓ და სხვა სპეციალიზებული სავაჭრო სიტუაციები.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'როგორ მუშაობს Market Shock Detector?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>Market Shock Detector ეძებს:</p>
                        <ul>
                            <li>✓ ულტრა-სწრაფ კოეფიციენტურ მოძრაობებს;</li>
                            <li>✓ ბაზრის შოკებს;</li>
                            <li>✓ ინფორმაციის გაჟონვით გამოწვეულ ფასების ცვლილებებს;</li>
                            <li>✓ მნიშვნელოვანი ინსაიდერული მოვლენების ეფექტს.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'როგორ მუშაობს Prematch Drops?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>Prematch Drops აფიქსირებს:</p>
                        <ul>
                            <li>✓ საწყის კოეფიციენტურ მოძრაობებს;</li>
                            <li>✓ ბაზრის ადრეულ ტენდენციებს;</li>
                            <li>✓ ტრენდის ფორმირების პირველ ნიშნებს.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'არის თუ არა პლატფორმა რეალურ დროში?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>დიახ.</strong> BF Markets უზრუნველყოფს:</p>
                        <ul>
                            <li>✓ ულტრა სწრაფ ფასების განახლებას;</li>
                            <li>✓ რეალურ დროში მონიტორინგს;</li>
                            <li>✓ მყისიერ ალერტებს;</li>
                            <li>✓ ბაზრებს შორის კორელაციების მონიტორინგს.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'შემიძლია რამდენიმე მოწყობილობიდან შესვლა?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>დიახ.</strong></p>
                        <p>თუმცა უსაფრთხოების სისტემა აკონტროლებს ერთდროულ აქტივობებს და საეჭვო ქცევის შემთხვევაში შესაძლოა მოითხოვოს დამატებითი ვერიფიკაცია.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'შეიძლება თუ არა ერთი გამოწერით რამდენიმე ადამიანმა ისარგებლოს?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>არა.</strong> ერთი გამოწერა განკუთვნილია მხოლოდ ერთი მომხმარებლისთვის.</p>
                        <p>პლატფორმის უსაფრთხოების სისტემა სპეციალურად არის შექმნილი ისე, რომ თავიდან აიცილოს ერთი ანგარიშის ერთდროულად რამდენიმე პირის მიერ გამოყენება.</p>
                        <p class="mt-2 text-[rgb(var(--danger))] bg-[rgb(var(--danger)_/_0.10)] p-3 rounded-lg border border-[rgb(var(--danger)_/_0.25)] text-sm">თუ რაიმე მიზეზით ასეთი ქმედება დაფიქსირდა, ეს ჩაითვლება მომსახურების წესების დარღვევად და BF Markets იტოვებს უფლებას: დროებით შეაჩეროს ანგარიში; შეზღუდოს წვდომა; ან სამუდამოდ დაბლოკოს მომხმარებელი.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'არსებობს უფასო საცდელი პერიოდი?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>დიახ.</strong> ყველა ახალ მომხმარებელს ეძლევა:</p>
                        <p class="text-2xl text-center my-4">🎁 7-დღიანი უფასო საცდელი პერიოდი</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'საცდელი პერიოდის დროს ავტომატურად ჩამომეჭრება თანხა?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>არა.</strong> ❌ საცდელი პერიოდის განმავლობაში თანხა ავტომატურად არ ჩამოიჭრება.</p>
                        <p>სერვისის გაგრძელებისთვის მომხმარებელმა თავად უნდა აირჩიოს ერთ-ერთი ფასიანი გეგმა.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'როგორ მუშაობს ავტომატური განახლება?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>ავტომატური განახლება აქტიურდება მხოლოდ იმ შემთხვევაში, თუ:</p>
                        <ul>
                            <li>✓ მომხმარებელმა ერთხელ მაინც გადაიხადა ფასიანი გამოწერა;</li>
                            <li>✓ თავად დაადასტურა Auto Renewal ფუნქცია.</li>
                        </ul>
                        <p class="mt-2">თუ მომხმარებელი არ ჩართავს ავტომატურ განახლებას, მომსახურების ვადის დასრულების შემდეგ გადახდა ხელით უნდა განახორციელოს.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'მაქვს იდეა ან სურვილი ახალი ფუნქციის შესახებ. შემიძლია გაგიზიაროთ?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>რა თქმა უნდა.</strong> ფუტერში განთავსებულია:</p>
                        <p class="text-2xl text-center my-4">💡 Feature Request</p>
                        <p>სადაც შეგიძლიათ:</p>
                        <ul>
                            <li>✓ აღწეროთ თქვენი იდეა;</li>
                            <li>✓ გაგვიზიაროთ სასურველი ფუნქციონალი;</li>
                            <li>✓ გამოგვიგზავნოთ წინადადებები.</li>
                        </ul>
                        <p class="mt-2 text-sm text-[rgb(var(--text-muted))]">ყველა მოთხოვნა ინახება და ეგზავნება ადმინისტრაციას განსახილველად.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'რამდენად სწრაფად ახლდება მონაცემები?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>BF Markets იყენებს მაღალსიჩქარიან ინფრასტრუქტურას, რათა ბაზრის ცვლილებები, კოეფიციენტების მოძრაობა და ლიკვიდობის განახლებები მომხმარებელმა <strong>რეალურ დროში</strong> მიიღოს.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'არის თუ არა BF Markets განკუთვნილი დამწყებებისთვის?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>დიახ.</strong> პლატფორმა განკუთვნილია როგორც:</p>
                        <ul>
                            <li>✓ დამწყები მომხმარებლებისთვის;</li>
                            <li>✓ პროფესიონალი ტრეიდერებისთვის;</li>
                            <li>✓ ანალიტიკოსებისთვის;</li>
                            <li>✓ სპორტული ბირჟების აქტიური მომხმარებლებისთვის.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'რა არის BF Markets-ის მთავარი მიზანი?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>ჩვენი მისიაა:</strong></p>
                        <blockquote class="italic text-lg text-[rgb(var(--text-soft))] border-l-4 border-[rgb(var(--primary))] pl-4 py-2 my-2">
                            „ქაოსიდან გამოვკვეთოთ შესაძლებლობა და მომხმარებელს ერთ ეკრანზე მივაწოდოთ ის სიგნალები, რომელთა აღმოჩენასაც სხვა შემთხვევაში საათები დასჭირდებოდა.“
                        </blockquote>
                    </div>
                HTML,
            ],
            [
                'question' => 'როგორ დაგიკავშირდეთ?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p class="text-xl text-center py-4">📧 <a href="mailto:office@betfairmarkets.com" class="text-[rgb(var(--primary))] hover:underline font-medium">office@betfairmarkets.com</a></p>
                    </div>
                HTML,
            ],
        ],
        'en' => [
            [
                'question' => 'What is BF Markets?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>BF Markets</strong> is a sports exchange data analytics platform that provides:</p>
                        <ul class="my-2 pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:content-['✅']">
                            <li> Real-time odds</li>
                            <li> Liquidity analysis</li>
                            <li> Market depth visualization</li>
                            <li> Dynamic charts</li>
                            <li> Smart filters</li>
                            <li> Cross-market correlation monitoring</li>
                            <li> Instant alerts and notifications</li>
                        </ul>
                        <p class="mt-2 text-[rgb(var(--warning))] bg-[rgb(var(--warning)_/_0.10)] p-3 rounded-lg border border-[rgb(var(--warning)_/_0.25)] text-sm">⚠️ BF Markets is not a bookmaker, does not accept bets, and does not provide gambling services.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'What is Sports Exchange Analytics?',
                'answer' => <<<HTML
                <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>Sports exchange analytics is the process of monitoring and analyzing real-time market data, including:</p>
                        <ul>
                            <li>Odds movements;</li>
                            <li>Liquidity levels;</li>
                            <li>Market depth;</li>
                            <li>Correlations between different markets;</li>
                            <li>Price dynamics and market direction.</li>
                        </ul>
                        <p class="mt-3"><strong>The main advantage of BF Markets</strong> is that users can obtain a complete view of the sports exchange markets from a single workspace, without constantly switching between pages, spending time searching, or missing important opportunities.</p>
                        <p class="mt-2">The platform allows users to:</p>
                        <ul>
                            <li>✓ See where the money is flowing in real time;</li>
                            <li>✓ Identify the most liquid and active markets;</li>
                            <li>✓ Monitor rising and falling odds;</li>
                            <li>✓ Analyze market direction and price dynamics;</li>
                            <li>✓ Track multiple sports and markets simultaneously;</li>
                            <li>✓ Quickly discover significant market changes and emerging opportunities.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'What is a Betting Exchange Data Terminal?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>A betting exchange data terminal is a professional software platform that provides:</p>
                        <ul>
                            <li>Real-time odds;</li>
                            <li>Liquidity data;</li>
                            <li>Market depth;</li>
                            <li>Advanced analytical tools.</li>
                        </ul>
                        <p class="mt-2"><strong>BF Markets</strong> is often described as a <em>"Bloomberg Terminal for sports exchange markets."</em></p>
                    </div>
                HTML,
            ],
            [
                'question' => 'How does liquidity tracking work?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>Liquidity tracking monitors the amount of money available at different price levels within exchange markets.</p>
                        <p class="mt-2">Our platform visualizes this information through:</p>
                        <ul>
                            <li>✓ Heatmaps;</li>
                            <li>✓ Order Book depth charts;</li>
                            <li>✓ Market dynamics analysis.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'What markets do you cover?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>We cover a wide range of exchange markets, including:</p>
                        <ul>
                            <li>✓ Match Odds;</li>
                            <li>✓ Over/Under;</li>
                            <li>✓ Correct Score;</li>
                            <li>✓ Double Chance;</li>
                            <li>✓ Asian Handicap;</li>
                            <li>✓ Specialized exchange markets.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'Which sports are available?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>BF Markets covers all sports currently supported by the global Betfair Exchange, including:</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">⚽ Football</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🎾 Tennis</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏀 Basketball</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏏 Cricket</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏈 American Football</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🎮 eSport</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🎯 Darts</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🎱 Snooker</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏒 Ice Hockey</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏉 Rugby</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🥊 Boxing</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">⚾ Baseball</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🏐 Volleyball</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🤾 Handball</span>
                            <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3.5 py-1 text-[0.85rem] font-medium text-[rgb(var(--text-soft))] transition-colors hover:bg-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))]">🥋 MMA</span>
                        </div>
                        <p class="mt-2 text-sm text-[rgb(var(--text-muted))]">…and many additional sports available on Betfair Exchange.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'Do you provide Horse Racing analytics?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>Yes. BF Markets already provides daily Horse Racing analysis, updated every day, including:</p>
                        <ul>
                            <li>Detailed race analysis and horse selections</li>
                            <li>Expert consensus and market ratings</li>
                            <li>Tactical previews and key runner assessments</li>
                        </ul>
                        <p>We are currently developing real-time Betfair market charts and live odds movement monitoring for Horse Racing.</p>
                        <p class="mt-2">As soon as these features are ready, they will be automatically integrated into the Horse Racing section, providing a complete market analytics experience.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'What are Smart Filters?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>Smart Filters allow users to instantly find:</p>
                        <ul>
                            <li>✓ Fav Losing;</li>
                            <li>✓ HT 0-0;</li>
                            <li>✓ 0-0 @ 70';</li>
                            <li>✓ Prematch Drops;</li>
                            <li>✓ Market Shock Detector;</li>
                            <li>✓ And many other specialized trading situations.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'How does Market Shock Detector work?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>Market Shock Detector identifies:</p>
                        <ul>
                            <li>✓ Ultra-fast odds movements;</li>
                            <li>✓ Market shocks;</li>
                            <li>✓ Information-driven price changes;</li>
                            <li>✓ The impact of significant insider events.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'How does Prematch Drops work?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>Prematch Drops identifies:</p>
                        <ul>
                            <li>✓ Initial market movements;</li>
                            <li>✓ Early market trends;</li>
                            <li>✓ The first signs of emerging price action.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'Is the platform real-time?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>Yes.</strong> BF Markets provides:</p>
                        <ul>
                            <li>✓ Ultra-fast price updates;</li>
                            <li>✓ Real-time market monitoring;</li>
                            <li>✓ Instant alerts and notifications;</li>
                            <li>✓ Cross-market correlation tracking.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'Can I access my account from multiple devices?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>Yes.</strong></p>
                        <p>However, our security systems monitor simultaneous activity and may request additional verification if suspicious behavior is detected.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'Can multiple people use one subscription?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>No.</strong> Each subscription is intended for a single user only.</p>
                        <p>Our platform includes advanced security mechanisms specifically designed to prevent account sharing.</p>
                        <p class="mt-2 text-[rgb(var(--danger))] bg-[rgb(var(--danger)_/_0.10)] p-3 rounded-lg border border-[rgb(var(--danger)_/_0.25)] text-sm">If account sharing is detected, BF Markets reserves the right to: temporarily suspend the account; restrict access; or permanently terminate the account.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'Do you offer a free trial?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']" >
                        <p><strong>Yes.</strong> Every new user receives:</p>
                        <p class="text-2xl text-center my-4">🎁 7-Day Free Trial</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'Will I be charged automatically during the free trial?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>No.</strong> ❌ No payment is charged during the trial period.</p>
                        <p>To continue using the service after the trial ends, you must manually choose one of the paid subscription plans.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'How does Auto-Renewal work?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>Auto-Renewal is activated only if:</p>
                        <ul>
                            <li>✓ You have completed at least one paid subscription purchase;</li>
                            <li>✓ You explicitly enable the Auto-Renewal option.</li>
                        </ul>
                        <p class="mt-2">If Auto-Renewal is not enabled, you will need to renew your subscription manually when it expires.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'I have an idea for a new feature. Can I share it?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>Absolutely.</strong> In the footer, you will find:</p>
                        <p class="text-2xl text-center my-4">💡 Feature Request</p>
                        <p>There you can:</p>
                        <ul>
                            <li>✓ Describe your idea;</li>
                            <li>✓ Suggest new functionality;</li>
                            <li>✓ Submit improvement requests and feedback.</li>
                        </ul>
                        <p class="mt-2 text-sm text-[rgb(var(--text-muted))]">All requests are stored and forwarded to our administration team for review.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'How fast is the data updated?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p>BF Markets uses high-performance infrastructure to deliver market movements, odds changes, and liquidity updates in <strong>real time</strong>.</p>
                    </div>
                HTML,
            ],
            [
                'question' => 'Is BF Markets suitable for beginners?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>Yes.</strong> The platform is designed for:</p>
                        <ul>
                            <li>✓ Beginners;</li>
                            <li>✓ Professional traders;</li>
                            <li>✓ Analysts;</li>
                            <li>✓ Active sports exchange users.</li>
                        </ul>
                    </div>
                HTML,
            ],
            [
                'question' => 'What is the mission of BF Markets?',
                'answer' => <<<HTML
                    <div class="px-6 pb-5 text-[0.95rem] leading-7 text-[rgb(var(--text-soft))] [&_ul]:my-2 [&_ul]:pl-5 [&_li]:relative [&_li]:list-none [&_li]:pl-5 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-[rgb(var(--primary))] [&_li]:before:content-['•']">
                        <p><strong>Our mission is:</strong></p>
                        <blockquote class="italic text-lg text-[rgb(var(--text-soft))] border-l-4 border-[rgb(var(--primary))] pl-4 py-2 my-2">
                            "To extract opportunity from chaos and deliver meaningful market signals on a single screen—signals that would otherwise require hours of searching and analysis to discover."
                        </blockquote>
                    </div>
                HTML,
            ],
            [
                'question' => 'How can I contact you?',
                'answer' => <<<HTML
                    <div class="bg-[rgb(var(--surface))] px-[22px] pb-[22px]">
                        <p class="text-xl text-center py-4">
                            📧 <a href="mailto:office@betfairmarkets.com" class="font-medium text-[rgb(var(--primary))] hover:underline">
                                office@betfairmarkets.com
                            </a>
                        </p>
                    </div>
                HTML,
            ],
        ],
    ];
@endphp


@section('content')
    <div data-accordion class="betguru-auth-bg min-h-screen">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <div class="rounded-[2rem] p-6 sm:p-8 lg:p-10 betguru-card-elevated">
                    <h1 class="mt-5 text-3xl font-bold theme-text-main sm:text-4xl">
                        <span class="text-[28px] mr-3">📖</span>{{ __('Frequently Asked Questions (FAQ)') }}
                    </h1>

                    @php $array = []; @endphp
                    @if (current_site_locale() == 'ka')
                        @php $array = $faqs['ka']; @endphp
                    @else
                        @php $array = $faqs['en']; @endphp
                    @endif

                    @foreach ($array as $index => $faq)
                        <div data-accordion-item class="mb-3 mt-4 overflow-hidden rounded-xl border border-[rgb(var(--border))] transition-colors duration-200 hover:border-[rgb(var(--border))]">
                            <button type="button"
                                data-accordion-toggle aria-expanded="false"
                                class="flex w-full cursor-pointer items-center justify-between border-0 bg-[rgb(var(--surface))] px-[22px] py-[18px] text-left font-['Inter','Noto_Sans_Georgian',sans-serif] text-base font-semibold leading-[1.4] text-[rgb(var(--text-main))] transition-colors duration-150 hover:bg-[rgb(var(--surface-soft))] max-sm:px-4 max-sm:py-3.5 max-sm:text-sm">
                                <span>
                                    <span class="mr-2.5 font-bold text-[rgb(var(--primary))]">{{ $index + 1 }}.</span>
                                    {{ $faq['question'] }}
                                </span>
                                <span data-accordion-arrow class="ml-3 shrink-0 text-xl text-[rgb(var(--text-muted))] transition-transform duration-200">▼</span>
                            </button>
                            <div data-accordion-panel class="bg-[rgb(var(--surface))]" hidden>
                                {!! $faq['answer'] !!}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
