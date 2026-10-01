<?php

/*
 * Pet lifecycle push copy (App\Services\PetPushService). Every key exists
 * as `_m` and `_f`: Arabic is gendered and both files share the key set
 * (PET-07). English mostly repeats itself; the pronouns differ.
 */
return [
    'replenish_title_m' => ':name\'s food might be running low 🥣',
    'replenish_title_f' => ':name\'s food might be running low 🥣',
    'replenish_body_m' => 'It\'s been about :days days since the last bag. Reorder in a tap.',
    'replenish_body_f' => 'It\'s been about :days days since the last bag. Reorder in a tap.',

    'birthday_title_m' => 'Happy birthday, :name! 🎂',
    'birthday_title_f' => 'Happy birthday, :name! 🎂',
    'birthday_body_m' => ':name turns :age today. Treat him to something special.',
    'birthday_body_f' => ':name turns :age today. Treat her to something special.',
    'birthday_code' => ' Use code :code for a birthday treat.',

    'adult_title_m' => ':name is all grown up 🎉',
    'adult_title_f' => ':name is all grown up 🎉',
    'adult_body_m' => ':name is 1 now: time to move him from :baby food to adult food.',
    'adult_body_f' => ':name is 1 now: time to move her from :baby food to adult food.',
    'baby_cat' => 'kitten',
    'baby_dog' => 'puppy',

    'senior_title_m' => ':name is a senior now 🧡',
    'senior_title_f' => ':name is a senior now 🧡',
    'senior_body_m' => 'At 7, senior food is gentler on his joints and tummy.',
    'senior_body_f' => 'At 7, senior food is gentler on her joints and tummy.',

    'reminder_title_m' => 'Time to restock :item for :name',
    'reminder_title_f' => 'Time to restock :item for :name',
    'reminder_body_m' => 'You asked us to remind you every :weeks weeks. Tap to reorder.',
    'reminder_body_f' => 'You asked us to remind you every :weeks weeks. Tap to reorder.',

    'years_1' => '1 year',
    'years_n' => ':n years',
];
