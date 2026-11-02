@extends('admin.layout')
@section('title', '帮助')
@section('content')
    @php $owner = $role === 'owner'; @endphp
    @php
        $can = fn (string $need) => match ($need) { 'any' => true, 'owner' => $role === 'owner', default => auth()->user()->allows($need) };
        $tasks = array_values(array_filter([
            ['回复客户', '打开收件箱，选一个聊天，输入后按 Enter。', route('inbox'), 'chat', 'inbox'],
            ['修改营业时间', '设置客户可以预约的日期和时间。', route('booking.setup'), 'clock', 'booking'],
            ['添加服务或修改价格', '每个服务的名称、分钟数和价格。', route('booking.setup'), 'tag', 'booking'],
            ['修改机器人说的话', '用英文和中文编辑菜单，然后发布。', route('menu'), 'edit', 'menu'],
            ['教机器人一个答案', '停车、付款方式、政策：写一次就够。', route('ai'), 'edit', 'ai'],
            ['在手机上收到提醒', '即使页面关闭，也会为新聊天弹出提醒。', route('account').'#phone-alerts', 'bell', 'any'],
            ['群发消息给多位客户', '把优惠发给一组客户，只发给同意接收的人。', route('broadcasts'), 'send', 'marketing'],
            ['查看销售和营销数据', '收入、渠道、最佳客户和该挽回的客户。', route('reports'), 'chart', 'reports'],
            ['添加团队成员', '给某人专属的登录账号和提醒。', route('settings', ['tab' => 'team']), 'people', 'owner'],
            ['选择每个人可以使用什么', '为一个人或多个人开启或关闭功能。', route('settings', ['tab' => 'team']), 'people', 'owner'],
            ['下载我的数据', '把客户、预约、订单和聊天下载成表格。', route('backup'), 'download', 'owner'],
        ], fn ($t) => $can($t[4])));
        $icons = [
            'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'tag' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
            'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
            'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
            'send' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>',
            'chart' => '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>',
            'people' => '<path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M21 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
            'download' => '<path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/>',
        ];
        $topics = [
            ['overview', '整体介绍', '系统从头到尾做什么'],
            ['day', '与系统度过的一天', '简单的每日流程'],
            ['reach', '客户如何联系您', '网站、WhatsApp、Messenger、Instagram'],
            ['inbox', '收件箱与转人工', '机器人还是真人、备注、标签、回复'],
            ['menu', '聊天菜单', '机器人显示和说的内容'],
            ['booking', '预约与日程', '服务、时间、更改、提醒'],
            ['customers', '客户、线索与标签', '他们是谁，离预约有多近'],
            ['orders', '订单与付款', '已付、待付、到店付款'],
            ['marketing', '营销与评价', '评分、评价链接、群发'],
            ['reports', '报表', '销售、营销等，可按渠道查看'],
            ['alerts', '提醒与等待中的客户', '铃铛、手机、邮件、提醒'],
            ['ai', 'AI 助手', '可选，默认关闭'],
            ['team', '团队与角色', '角色，以及每个人可以使用什么'],
            ['billing', '方案与账单', '试用、上限、付款'],
            ['data', '您的数据与隐私', '导出、备份、删除'],
            ['faq', '常见问题与解决办法', '遇到问题时怎么办'],
        ];
    @endphp

    <div class="page-head">
        <div><h1>帮助</h1></div>
        @if ($owner)<a class="btn" href="{{ route('guide') }}">打开设置向导</a>@endif
    </div>

    <section class="hp-hero">
        <h2>需要什么帮助？</h2>
        <p>用日常的话搜索，或选一件您想做的事。</p>
        <div class="hp-searchbox"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg><input type="search" id="hp-search" placeholder="试试：营业时间、模板、标签、停止、备份" aria-label="搜索帮助"></div>
        <div class="hp-chips">@foreach (['营业时间', '快捷回复', '标签', '提醒', '模板', '备份'] as $w)<button type="button" data-q="{{ $w }}">{{ $w }}</button>@endforeach</div>
    </section>

    <div class="hp-discover" id="hp-discover">
        <h3 class="set-group">我想要…</h3>
        <div class="hp-tasks">
            @foreach ($tasks as [$t, $d, $url, $ic])
                <a class="hp-task" href="{{ $url }}"><span class="hp-ico"><svg viewBox="0 0 24 24">{!! $icons[$ic] !!}</svg></span><span><b>{{ $t }}</b><small>{{ $d }}</small></span></a>
            @endforeach
        </div>

        <h3 class="set-group">一次聊天的流程</h3>
        <div class="hp-pipe">
            <div><span>1</span><b>客户发来消息</b><small>网站、WhatsApp、Messenger 或 Instagram</small></div>
            <div><span>2</span><b>机器人回答</b><small>菜单、价格，还能自己完成预约</small></div>
            <div><span>3</span><b>真人接手</b><small>客户要求时，或机器人答不上时</small></div>
            <div><span>4</span><b>全部自动保存</b><small>客户、聊天、预约、标签、客户等级</small></div>
            <div><span>5</span><b>后续跟进</b><small>评分、评价链接、邀请回访</small></div>
        </div>

        <h3 class="set-group">按主题浏览</h3>
        <div class="hp-topics">
            @foreach ($topics as $i => [$id, $t, $d])
                <a href="#{{ $id }}" class="hp-topic"><i style="--n: {{ $i }}"></i><span><b>{{ $t }}</b><small>{{ $d }}</small></span></a>
            @endforeach
        </div>
    </div>

    <div class="hp">
        <aside class="hp-toc" id="hp-toc">
            <nav>
                <a href="#overview">整体介绍</a>
                <a href="#day">与系统度过的一天</a>
                <a href="#reach">客户如何联系您</a>
                <a href="#inbox">收件箱与转人工</a>
                <a href="#menu">聊天菜单</a>
                <a href="#booking">预约与日程</a>
                <a href="#customers">客户、线索与标签</a>
                <a href="#orders">订单与付款</a>
                <a href="#marketing">营销与评价</a>
                <a href="#reports">报表</a>
                <a href="#alerts">提醒与等待中的客户</a>
                <a href="#ai">AI 助手</a>
                <a href="#team">团队与角色</a>
                <a href="#billing">方案与账单</a>
                <a href="#data">您的数据与隐私</a>
                <a href="#faq">常见问题与解决办法</a>
                <a href="#words">本页用到的词语</a>
            </nav>
        </aside>

        <div class="hp-main" id="hp-main">
            <section class="card hp-sec" id="overview">
                <h2>整体介绍</h2>
                <p>这个系统会替您回答客户、帮他们预约，并在需要真人时把聊天交给真人。它支持英文和中文，并把客户的所有资料集中在一个地方。</p>
                <ol class="hp-flow">
                    <li><b>客户发来消息</b>，来源可以是网站聊天、WhatsApp、Messenger 或 Instagram。</li>
                    <li><b>机器人回答</b>，用菜单（价格、预约、营业时间、位置）回应，还能自己完成预约。</li>
                    <li><b>真人接手</b>：客户要求真人，或机器人听不懂时，团队会收到提醒。</li>
                    <li><b>全部自动保存</b>：客户、聊天、预约、标签和客户等级。您可以在“今天”“收件箱”“日程”和“客户”里看到。</li>
                    <li><b>到访之后</b>，系统可以邀请客户评分、留下评价，并邀请客户再次光临。</li>
                </ol>
                <p class="hp-tip"><b>小提示</b> 在任何页面按 <kbd>Ctrl</kbd> + <kbd>K</kbd>，可以快速跳到某位客户或某个聊天。</p>
                <p class="mute">左侧菜单就是地图，分成几组。<b>日常工作</b>：今天、收件箱、日程、客户、任务和订单。<b>成长</b>：营销和报表。<b>聊天机器人</b>：菜单编辑器、AI 助手、快捷回复和渠道。<b>业务设置</b>：设置向导、预约设置和设置。您只会看到店主为您开放的部分。</p>
            </section>

            <section class="card hp-sec" id="day">
                <h2>与系统度过的一天</h2>
                <ol class="hp-steps">
                    <li><b>早上，打开<a href="{{ route('today') }}">今天</a>。</b>每日摘要会显示昨天的聊天、预约和收入，以及现在需要您处理的事（等待中的聊天、逾期任务、明天的预约）。</li>
                    <li><b>在<a href="{{ route('inbox') }}">收件箱</a>回复。</b>标有<i>需要人工</i>的聊天排在最前面。点“回复”后机器人会安静下来。处理完点<b>标为已解决</b>。如果客户再次发来消息，聊天会自动重新打开。</li>
                    <li><b>查看<a href="{{ route('schedule') }}">日程</a>。</b>打开一个预约，可以查看客户、标为完成、记录付款或改期。</li>
                    <li><b>处理您的<a href="{{ route('tasks') }}">任务</a>。</b>跟进、回电，任何您不想忘记的事。</li>
                    <li><b>一天结束：</b>没有什么需要关闭的。如果您在<a href="{{ route('account') }}">我的账户</a>里开启了提醒，提醒会发到您的手机。</li>
                </ol>
            </section>

            <section class="card hp-sec" id="reach">
                <h2>客户如何联系您</h2>
                <ul>
                    <li><b>网站聊天。</b>一个链接和一个小聊天框，可放在您的网站上。可从<b>今天</b>（复制聊天链接）或“设置 → 生意与团队”复制链接。</li>
                    <li><b>WhatsApp、Messenger、Instagram。</b>在<a href="{{ route('channels') }}">渠道</a>中连接 @if (! $owner)<span class="tag">店主</span>@endif。您发出的回复会出现在客户自己的应用里。</li>
                    <li><b>语言。</b>客户先选择 English 或中文，机器人会用该语言回答。它也看得懂繁体中文输入。</li>
                    <li><b>客户随时可以输入的词：</b><code>menu</code>（回到菜单）、<code>human</code> 或 <code>人工</code>（找真人）、<code>back</code>（返回上一步）、<code>stop</code>（不再接收营销消息）。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="inbox">
                <h2>收件箱与转人工</h2>
                <ul>
                    <li><b>机器人回答中 / 您正在处理。</b>聊天顶部的标记显示现在是谁在和客户交谈。您一回复，就成为该聊天的负责人，机器人保持安静。</li>
                    <li><b>交还给机器人</b>（在 <b>···</b> 菜单中）让机器人继续。<b>标为已解决</b>会关闭聊天；客户再次发消息时会重新打开。</li>
                    <li><b>内部备注。</b>只有您的团队看得到，客户永远看不到。</li>
                    <li><b>快捷回复。</b>在回复框下面选一条，或点系统根据客户问题找到的<b>推荐</b>回复。{name} 和 {business} 会自动填入，并使用客户的语言。</li>
                    <li><b>标签。</b>根据客户写的内容自动添加（价格咨询、预约帮助、投诉……）。点标签可移除，也可以用<b>+ 标签</b>添加自己的。</li>
                    <li><b>负责人</b>可把聊天分配给团队成员。<b>+ 任务</b>可创建跟进事项。</li>
                    <li class="hp-tipli"><b>小提示。</b>在回复框中，<kbd>Enter</kbd> 发送，<kbd>Shift</kbd> + <kbd>Enter</kbd> 换行。</li>
                    <li><b>WhatsApp 24 小时规则。</b>客户最后一条消息之后的 24 小时内，WhatsApp 才允许您自由回复。之后只能发送已审核的<b>模板</b>。出现这种情况时，收件箱会提示您。</li>
                                    <li><b>未读聊天是蓝色的。</b>带蓝色边、名字加粗并有一个圆点的聊天，表示有客户消息还没有团队成员打开过。打开后就恢复正常。您自己的回复不会让它变成未读。<b>需要人工</b>的标记就在名字旁边。</li>
                    <li><b>按渠道筛选。</b>在“进行中 / 已解决 / 全部”旁边，选择 WhatsApp、Messenger、Instagram 或网站，只列出该渠道的聊天。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="menu">
                <h2>聊天菜单 @unless ($owner)<span class="tag">店主</span>@endunless</h2>
                <p class="hp-tip"><b>小提示</b> 编辑后一定要点<b>发布</b>。在此之前，客户看到的仍是旧菜单。</p>
                <p><a href="{{ route('menu') }}">菜单编辑器</a>决定机器人说什么、显示什么。它由一组页面组成：一段消息、按钮，以及每个按钮通向哪里。</p>
                <ul>
                    <li>用英文和中文编辑文字。点<b>发布</b>让修改生效。发布之前，客户仍看到旧菜单。</li>
                    <li><b>预览</b>让您像客户一样试用，而不会产生真实聊天。</li>
                    <li>按钮上的<b>关键词</b>是客户可以输入来代替点按钮的词（例如“price”“多少钱”）。</li>
                    <li>机器人自己说的固定句子（抱歉、预约确认、提醒）已内置并翻译好。</li>
                                    <li><b>新步骤需要一个按钮。</b>您新增的步骤只会出现在列表中。打开<b>主菜单</b>步骤，点<b>+ 添加按钮</b>，在<b>跳转到</b>中选您的新步骤，然后<b>发布</b>。</li>
                    <li><b>随步骤发送图片或文件</b>（价目表、地图）：在步骤中选择文件（JPG、PNG、PDF、MP4 或 Office 文件，最大 16 MB）。机器人会在该步骤的文字之前发送它。</li>
                    <li><b>英文和中文框。</b>如果中文框是空的，中文客户会看到英文内容，框下面会提醒您。<b>从 English 翻译</b>会用 AI 填写（需要已连接真正的 AI）。保存前请务必看一遍结果。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="booking">
                <h2>预约与日程</h2>
                <ul>
                    <li><b>预约设置</b> @unless ($owner)<span class="tag">店主</span>@endunless：您的服务（名称、分钟数、价格）、提供服务的人、营业时间、休息日，以及规则，例如客户最晚在到访前多少小时可以取消或更改。</li>
                    <li><b>客户在聊天中预约。</b>他们选择服务、日期、时间，然后留下姓名和电话。只会提供空闲的时间，所以不会重复预约。</li>
                    <li><b>客户更改或取消</b>：在菜单中选<i>我的预约</i> → 更改时间或取消。比您的规则更晚的更改会转给您的团队。员工会收到铃铛提醒。</li>
                    <li><b>提醒</b>会在到访前 24 小时和 2 小时发给客户。</li>
                    <li><b>Google 日历</b>（可选）：给每位服务人员一个日历，预约会出现在那里，而那里的忙碌时间会在聊天中被屏蔽。</li>
                    <li>在<a href="{{ route('schedule') }}">日程</a>中可以查看一周或一个月。点一个预约可打开，或把已确认的预约拖到新的时间。颜色代表状态（已确认、已完成、未到）。</li>
                    <li>在预约上点<b>标为完成</b>，付款资料会打开，方便您记录客户付了多少，包括到店付款。</li>
                                    <li>日程有<b>月</b>、<b>周</b>、<b>日</b>和<b>列表</b>。<b>日</b>视图为每位服务人员显示一栏，生意忙的时候最好用。在周视图中点日期名称即可打开当天。在<b>列表</b>中，已结束但还没标为已完成或未到店的预约会排在最前面，不会被遗漏。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="customers">
                <h2>客户、线索与标签</h2>
                <ul>
                    <li>每位留下姓名或电话的人都会成为<a href="{{ route('customers') }}">客户</a>，他们的聊天、预约、订单、标签和备注都在同一个页面。</li>
                    <li><b>客户等级</b>显示某人离购买有多近：<b>随便看看</b>、<b>有兴趣</b>（问过价格或预约）、<b>准备预约</b>（已预约）。它只会自动升高，不会降低。</li>
                    <li><b>标签</b>：用于人（VIP、首次到访……）和聊天（投诉……）。在<a href="{{ route('tags') }}">设置 → 标签</a>中管理@unless ($role !== 'staff')（店主和经理）@endunless。自动打标签只靠词语匹配，不使用 AI，可在“设置 → 生意与团队”中关闭。</li>
                    <li>每个列表都有相同的筛选栏。筛选在您输入或选择时立刻生效；点<b>重置</b>可清除。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="orders">
                <h2>订单与付款</h2>
                <ul>
                    <li>当您在一次到访上点<b>标为完成</b>，或做成一笔生意时点<b>新建订单</b>，就会产生<a href="{{ route('orders') }}">订单</a>。</li>
                    <li>状态：<b>待处理</b>（尚未付款）、<b>已付款</b>、<b>失败</b>、<b>已退款</b>。到店付款的客户，由您直接标为已付款。</li>
                    <li>已收到的款项会计入<b>今天</b>和<b>报表</b>。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="marketing">
                <h2>营销与评价 @unless ($role !== 'staff')<span class="tag">店主和经理</span>@endunless</h2>
                <ul>
                    <li><b>自动化</b>：自己发送的消息。到访后：邀请评分（1 到 5）。满意的客户会收到您的评价链接；不满意的会提醒经理。其他还有：几周后邀请回访、未到后跟进、提醒问过某项服务的人。</li>
                    <li><b>群发</b>：一条消息发给多位客户，可按标签、客户等级或语言挑选。只包含同意接收营销的人，输入 <code>stop</code> 的人会被自动移除。</li>
                    <li><b>消息模板</b>：您主动发起的 WhatsApp 消息需要已审核的模板。在“营销 → 模板”中管理。</li>
                    <li><b>活动记录</b>：发给了谁、发了什么，以及调查答案和评价链接（第二个标签页）。</li>
                                    <li><b>自动化何时发送。</b>全部默认关闭。<b>满意度调查</b>：到访结束约 2 小时后（评 4 或 5 分可发送您的评价链接；1 或 2 分会提醒您的团队）。<b>再次预约提醒</b>：30 天没有到访后。<b>未到店挽回</b>：您标记未到店后 2 小时。<b>准备预约跟进</b>：24 小时后。文字和延迟时间都可以修改。</li>
                    <li><b>哪些人会被跳过。</b>从未同意接收消息或输入过 <code>stop</code> 的人、有人正在处理的聊天，以及在 WhatsApp、Messenger 和 Instagram 上最后发言已超过 24 小时的人（WhatsApp 可以用已审核的模板）。“报表 → 营销”会显示发送了什么、跳过了什么。它们只有在后台任务运行时才会执行。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="reports">
                <h2>报表</h2>
                <p><a href="{{ route('reports') }}">报表</a>显示生意背后的数据，可选任意时间段。在顶部选择时间段、渠道、服务或人员。每个表格都有<b>导出 CSV</b>，<b>打印 / 存为 PDF</b>会打印您当前所在的标签页。</p>
                <ul>
                    <li><b>概览</b>：聊天、新客户、预约和已付款订单，附逐日图表。</li>
                    <li><b>销售</b>：收到的款项、已付款订单、平均每单、等待付款、逾期和已退款；从第一条消息到付款的路径；按服务、人员、每周、星期几和<b>渠道</b>统计的收入；新客户与回头客；最佳客户和逾期订单。</li>
                    <li><b>营销</b>：客户从哪里来（每个渠道的聊天、消息、预约和收入）、他们问什么、什么时候来信、群发和自动消息的效果、评分，以及该挽回的客户。</li>
                    <li><b>预约、客户、收入</b>：最忙的日子和时段、服务和人员、回头客，以及付款。</li>
                    <li><b>渠道筛选。</b>选择 WhatsApp、Messenger、Instagram 或网站，只看该渠道。收入记在客户第一次联系时使用的渠道上（客户预约或留下资料之后）。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="alerts">
                <h2>提醒与等待中的客户</h2>
                <p class="hp-tip"><b>小提示</b> 在您使用的每部手机和电脑上都开启手机提醒。只需一分钟，就不会错过任何客户。</p>
                <ul>
                    <li><b>铃铛</b>（每个页面顶部）显示新聊天、预约和投诉，并会响铃。点一下即可开启声音。它只在页面打开时有效。</li>
                    <li><b>手机提醒</b>：在<a href="{{ route('account') }}">我的账户</a>中，为您使用的每部手机或电脑点“开启提醒”。即使页面关闭，也会弹出提醒。它需要 https 地址（线上网站），或同一台电脑上的 localhost。iPhone 需要先把网站添加到主屏幕。</li>
                    <li><b>没人回复等待中的客户时</b>，系统会在 5、10、20 和 30 分钟提醒团队。在 10 和 30 分钟时，还可以给店主和经理发邮件，并发送 WhatsApp 提醒。可在“设置 → 生意与团队”中设置。</li>
                    <li><b>在您选定的时间之后把聊天交还给机器人</b>，这样客户就不会被冷落。客户会收到抱歉的消息，并重新看到菜单。</li>
                    <li><b>每日摘要邮件</b>（可选）：每天早上约 8:00 发送昨天的数据。</li>
                                    <li><b>很长的等待</b>会用天和小时来表示（“3 天 22 小时”），而不是几千分钟。等待特别久通常说明后台任务没有运行：如果看到黄色横幅<i>自动任务没有在运行</i>，请运行一次 <code>tools\setup-scheduler.bat</code>。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="ai">
                <h2>AI 助手</h2>
                <p>AI <b>是可选的，默认关闭</b>，所以在您选择之前不会产生任何费用。菜单机器人和预约在没有它的情况下也完全可用。</p>
                <ul>
                    <li>在 <a href="{{ route('ai') }}">AI 助手</a> @unless ($role !== 'staff')<span class="tag">店主和经理</span>@endunless 中选择模式，并告诉它您的资料（价目表、政策）。它不会编造价格：含有您没给过的价格的答案会被拦截。</li>
                    <li>开启 AI 后，员工可以在聊天中点<b>用 AI 总结</b>，AI 也可以起草回复，由员工批准后再发送。</li>
                    <li>每个方案每月包含一定数量的 AI 回复。达到上限时，会有消息提醒您。</li>
                                    <li><b>教它。</b>您写的额外答案（停车、付款方式、政策）会在客户输入该主题时按原文发送，<b>即使关闭 AI 也可以</b>。给答案添加<b>关键词</b>（例如“厕所, 洗手间”）可以匹配更多说法。<b>机器人答不上来的问题</b>会列出客户输入后没有匹配到任何内容的话，并有按钮让您添加答案。</li>
                    <li><b>翻译按钮。</b>需要连接真正的 AI。<b>练习用 AI</b>只是模拟，所以它不会写入任何内容并会告诉您。每次翻译算一次 AI 调用。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="team">
                <h2>团队与角色</h2>
                <div class="hp-table">
                    <div><span class="tag ok">店主</span><p>所有功能，包括账单、渠道、菜单、预约设置、团队和备份。</p></div>
                    <div><span class="tag warn">经理</span><p>快捷回复、标签、营销、AI 和模板，以及员工能做的一切。</p></div>
                    <div><span class="tag">员工</span><p>收件箱、日程、客户、任务、订单和报表。</p></div>
                </div>
                <p class="mute">在“设置 → 生意与团队 → 团队”中添加成员。每个人都有自己的登录账号和提醒。</p>
                <ul>
                    <li><b>选择每个人可以使用什么</b> @unless ($owner)<span class="tag">店主</span>@endunless：点某人旁边的铅笔，勾选<b>自选此人可以使用的功能</b>。功能包括：收件箱、日程、客户、客户工具、任务、订单、报表、营销、AI 助手、快捷回复（编辑）、标签、菜单编辑器和预约设置。不勾选时，此人按其角色获得常规权限。渠道、账单、备份、设置和团队始终只有店主可用。</li>
                    <li><b>团队表格</b>显示每个人的角色、权限和一个<b>正常</b>开关（可立即开启或关闭账号）。钥匙按钮用来设置新密码，铅笔用来编辑。可以搜索、按角色或按“正常 / 已停用”筛选，也可以勾选多人，一次更改角色、开启或关闭账号，或恢复为常规权限。您不能停用自己，最后一位店主也会保留。</li>
                    <li>如果有人看到<i>您没有权限使用此功能</i>，请让店主为他开启该功能。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="billing">
                <h2>方案与账单 @unless ($owner)<span class="tag">店主</span>@endunless</h2>
                <ul>
                    <li>新生意从免费试用开始。<a href="{{ route('billing') }}">方案与账单</a>显示各项上限的使用情况（AI 回复、团队成员、人数、渠道）以及您的付款记录。</li>
                    <li>如果试用或付款结束，AI 会暂停，但客户的消息仍会进入您的收件箱。</li>
                    <li>您可以随时停止续订，并在已付费的这个月结束前继续使用方案。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="data">
                <h2>您的数据与隐私</h2>
                <ul>
                    <li><b>备份与导出</b> @unless ($owner)<span class="tag">店主</span>@endunless：在“设置 → 备份与导出”中，把客户、预约、订单和聊天下载成表格。服务器也会每晚备份数据库。</li>
                    <li><b>每个生意相互独立。</b>没有人能看到别的生意的客户或聊天。</li>
                    <li><b>客户的权利。</b>在客户页面上，可以下载关于他们的全部资料，或永久删除他们。输入 <code>stop</code> 的客户将不会再收到营销消息。</li>
                </ul>
            </section>

            <section class="card hp-sec" id="faq">
                <h2>常见问题与解决办法</h2>
                <details><summary>机器人没有回复某位客户。</summary><p>在收件箱中打开该聊天。如果显示<i>您正在处理</i>，说明机器人是有意保持安静的：在 ··· 菜单中点“交还给机器人”。如果是 WhatsApp、Messenger 或 Instagram，还要检查“渠道”里该渠道是否显示为已连接。</p></details>
                <details><summary>我无法给客户发送 WhatsApp 消息。</summary><p>这是 WhatsApp 的 24 小时规则：客户超过 24 小时没有发消息后，您只能发送已审核的模板。请在聊天中使用<b>发送模板</b>。</p></details>
                <details><summary>铃铛没有声音。</summary><p>浏览器会在您在页面上点一下之前屏蔽声音。请检查铃铛菜单是否显示“声音已开启”。声音只在页面打开时播放；页面关闭时请使用手机提醒。</p></details>
                <details><summary>手机提醒没有出现。</summary><p>请在该设备的“我的账户”中开启，并点<b>给我发一条测试提醒</b>。网站需要 https（或 localhost）。请确认浏览器允许该网站发送通知；iPhone 需要先把网站添加到主屏幕。</p></details>
                <details><summary>有个聊天自己回到了机器人。</summary><p>您在“设置 → 生意与团队”中设置了“一段时间后把聊天交还给机器人”，而且没人及时回复。客户已收到抱歉的消息，并重新看到菜单。</p></details>
                <details><summary>客户看不到空闲时间。</summary><p>请检查预约设置：该服务有提供服务的人、已设置营业时间、这些日子没有被标为休息，并且 Google 日历没有显示那位服务人员忙碌。</p></details>
                <details><summary>某个标签或客户等级不对。</summary><p>标签是根据词语猜出来的，所以有时会错。点标签即可移除。客户等级可以在客户页面上修改。</p></details>
                <details><summary>我改了菜单，但客户还是看到旧的。</summary><p>请在菜单编辑器中点<b>发布</b>。在此之前，修改只是草稿。</p></details>
                            <details><summary>我在菜单编辑器里新增了步骤，但客户看不到。</summary><p>步骤只能通过按钮到达。打开<b>主菜单</b>步骤，添加一个前往新步骤的按钮，然后点<b>发布</b>。在 WhatsApp 里输入 <code>menu</code> 就能看到新的列表。</p></details>
                <details><summary>机器人在 WhatsApp 或 Messenger 上不回复了。</summary><p>打开“渠道”，看<i>Meta 的消息有送到本系统吗？</i>的提示。使用免费隧道时，每次重启地址都会变：请在 <code>.env</code> 中更新 <code>APP_URL</code>，运行 <code>php artisan config:clear</code>，再点<b>帮我把这些保存到 Meta</b>。WhatsApp 的测试令牌只有 24 小时，如果聊天里出现关于令牌的红色提示，请重新生成一个。如果聊天显示<i>需要人工</i>，输入 <code>menu</code> 即可唤醒机器人。</p></details>
                <details><summary>翻译提示练习用 AI 只是模拟。</summary><p>练习用 AI 无法翻译。请自己填写中文，或连接真正的 AI 密钥（AI 助手 → 如何开启）。</p></details>
                <details><summary>聊天里出现“已经 ... 天没有人回复”。</summary><p>后台任务没有运行，所以等待中的聊天只有在它重新启动时才被检查。请运行一次定时任务设置，让它一直运行。</p></details>
            </section>

            <section class="card hp-sec" id="words">
                <h2>本页用到的词语</h2>
                <dl class="hp-dl">
                    <dt>转人工</dt><dd>机器人把聊天交给真人。</dd>
                    <dt>渠道</dt><dd>客户发消息的地方：网站、WhatsApp、Messenger、Instagram。</dd>
                    <dt>服务人员</dt><dd>提供服务并有自己日程的人（或房间、机器）。</dd>
                    <dt>客户等级</dt><dd>客户离预约有多近：随便看看、有兴趣、准备预约。</dd>
                    <dt>模板</dt><dd>经 WhatsApp 审核的消息，24 小时窗口关闭后您仍可发送。</dd>
                    <dt>群发</dt><dd>一条消息发给多位客户。</dd>
                    <dt>自动化</dt><dd>在某件事（例如到访）发生后自己发送的消息。</dd>
                    <dt>试用</dt><dd>开始使用时的免费期。</dd>
                </dl>
            </section>
            <section class="card hp-sec hp-stuck" id="stuck">
                <h2>还是解决不了？</h2>
                <p>请先看<a href="#faq">常见问题与解决办法</a>，它涵盖了最常见的问题。@if ($owner)<a href="{{ route('guide') }}">设置向导</a>会带您完成最初的几步。@endif 否则请联系帮您设置这个系统的人，并告诉他们您点了什么、期望看到什么。</p>
            </section>
            <p class="mute hp-none" id="hp-none" hidden>没有符合搜索的内容。请试试更短的词。</p>
        </div>
    </div>
@endsection
@push('scripts')
<script>
(function () {
  var box = document.getElementById('hp-search'), secs = document.querySelectorAll('#hp-main .hp-sec'), links = document.querySelectorAll('#hp-toc nav a'), none = document.getElementById('hp-none');
  var disc = document.getElementById('hp-discover');
  document.querySelectorAll('.hp-chips button').forEach(function (b) { b.addEventListener('click', function () { box.value = b.dataset.q; box.dispatchEvent(new Event('input')); box.focus(); }); });
  box.addEventListener('input', function () {
    var q = box.value.trim().toLowerCase(), shown = 0;
    disc.hidden = q !== '';
    secs.forEach(function (s) {
      var ok = !q || s.textContent.toLowerCase().indexOf(q) !== -1;
      s.hidden = !ok; if (ok) { shown++; }
      var a = document.querySelector('#hp-toc a[href="#' + s.id + '"]'); if (a) { a.hidden = !ok; }
    });
    none.hidden = shown > 0;
  });
  // Open the question you land on, and highlight the topic you are reading.
  var obs = 'IntersectionObserver' in window ? new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) { links.forEach(function (l) { l.classList.toggle('on', l.getAttribute('href') === '#' + e.target.id); }); } });
  }, { rootMargin: '-20% 0px -70% 0px' }) : null;
  if (obs) { secs.forEach(function (s) { obs.observe(s); }); }
})();
</script>
@endpush
