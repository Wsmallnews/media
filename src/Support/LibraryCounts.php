<?php

namespace Wsmallnews\Media\Support;

/**
 * 「库级计数」的请求内缓存（类型计数、未归类计数）。
 *
 * 为什么需要：一个表单里往往有多个 picker（官方演示页 20+ 个、Repeater 每行一个），
 * 它们算的是同一份数字，每个实例各跑一次 count(*) + 8 个 sum(case ...) 纯属浪费。
 *
 * 为什么不用 static：static 会跨请求 / 跨测试残留（测试里同一进程跑几百个用例，
 * 数据库每条都重建），表现就是计数忽大忽小。这里走容器 scoped 绑定——
 * 每个请求（以及每个测试用例新建的 app）一份，天然失效。
 */
class LibraryCounts
{
    /** @var array<string, array<string, int>> 键 = 租户 id */
    public array $typeCounts = [];

    /** @var array<string, int> 键 = 租户 id */
    public array $uncategorized = [];
}
