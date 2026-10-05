<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Services\Search\ItemSearch;
use App\Services\Search\SearchQuery;
use Tests\TestCase;

/**
 * Locks in how customer search text is cleaned and turned into SQL. These
 * only build queries (toSql), so they need no seeded database.
 */
class SearchQueryTest extends TestCase
{
    public function test_repeated_whitespace_never_produces_an_empty_term(): void
    {
        $search = SearchQuery::fromString("  chicken \t  burger  ");

        $this->assertSame(['chicken', 'burger'], $search->terms());
        $this->assertSame('chicken burger', $search->text());
    }

    public function test_blank_and_non_string_input_is_empty(): void
    {
        $this->assertTrue(SearchQuery::fromString('   ')->isEmpty());
        $this->assertTrue(SearchQuery::fromString(null)->isEmpty());
        $this->assertTrue(SearchQuery::fromString(['burger'])->isEmpty());
    }

    public function test_terms_are_deduplicated_and_capped(): void
    {
        $search = SearchQuery::fromString('a a b c d e f g');

        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $search->terms());
    }

    public function test_length_is_capped(): void
    {
        $search = SearchQuery::fromString(str_repeat('ب', 500));

        $this->assertSame(SearchQuery::MAX_LENGTH, mb_strlen($search->text()));
    }

    public function test_like_wildcards_in_user_text_are_escaped(): void
    {
        $this->assertSame('%50\\% off\\_x\\\\%', SearchQuery::contains('50% off_x\\'));
        $this->assertSame('50\\%%', SearchQuery::startsWith('50%'));
    }

    public function test_every_term_must_match_by_default(): void
    {
        $query = Item::withoutGlobalScopes();
        ItemSearch::applyTextMatch($query, SearchQuery::fromString('chicken burger'), ['items.name'], ['tags' => 'tag']);

        $sql = $query->toSql();
        $this->assertStringContainsString('((`items`.`name` like ? or exists', $sql);
        $this->assertStringContainsString(') and (`items`.`name` like ?', $sql);
        $this->assertSame(['%chicken%', '%chicken%', '%burger%', '%burger%'], $query->getBindings());
    }

    public function test_any_mode_accepts_a_single_matching_term(): void
    {
        $query = Item::withoutGlobalScopes();
        ItemSearch::applyTextMatch($query, SearchQuery::fromString('chicken burger'), ['items.name'], [], 'any');

        $this->assertStringContainsString('(`items`.`name` like ? or `items`.`name` like ?)', $query->toSql());
    }

    public function test_empty_search_matches_nothing(): void
    {
        $query = Item::withoutGlobalScopes();
        ItemSearch::applyTextMatch($query, SearchQuery::fromString(' '), ['items.name'], ItemSearch::RELATIONSHIPS);

        $this->assertStringContainsString('1 = 0', $query->toSql());
    }

    public function test_paging_input_is_clamped(): void
    {
        $this->assertSame(10, ItemSearch::limit(null));
        $this->assertSame(ItemSearch::MAX_LIMIT, ItemSearch::limit(100000));
        $this->assertSame(1, ItemSearch::limit(-5));
        $this->assertSame(1, ItemSearch::page('abc'));
        $this->assertSame(3, ItemSearch::page('3'));
    }

    public function test_id_lists_accept_json_or_arrays_and_drop_junk(): void
    {
        $this->assertSame([1, 2], ItemSearch::idList('[1,2]'));
        $this->assertSame(['3'], ItemSearch::idList(['3', 'x']));
        $this->assertSame([], ItemSearch::idList(''));
        $this->assertSame([], ItemSearch::idList('not json'));
    }
}
