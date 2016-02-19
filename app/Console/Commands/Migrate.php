<?php

namespace Coyote\Console\Commands;

use Coyote\Pm;
use Illuminate\Console\Command;
use DB;

ini_set('memory_limit', '1G');

class Migrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coyote:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate Coyote from 1.x to 2.0';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function skipPrefix($prefix, $data)
    {
        $result = [];

        foreach ($data as $key => $value) {
            $key = substr_replace($key, '', 0, strlen($prefix));
            $result[$key] = $value;
        }

        return $result;
    }

    private function rename(&$data, $oldKey, $newKey)
    {
        $data[$newKey] = $data[$oldKey];
        unset($data[$oldKey]);

        return $data;
    }

    private function timestampToDatetime(&$value)
    {
        $value = date('Y-m-d H:i:s', $value);

        return $value;
    }

    private function setNullIfEmpty(&$value)
    {
        if (empty($value)) {
            $value = null;
        }

        return $value;
    }

    private function skipHost($url)
    {
        $parsed = parse_url($url);

        $url = trim($parsed['path'], '/');
        if (!empty($parsed['query'])) {
            $url .= '?' . $parsed['query'];
        }
        if (!empty($parsed['fragment'])) {
            $url .= '#' . $parsed['fragment'];
        }
        if (!empty($parsed['host']) && $parsed['host'] == 'forum.4programmers.net') {
            $url = 'Forum/' . $url;
        }

        return $url;
    }

    private function count($tables)
    {
        $result = 0;

        if (!is_array($tables)) {
            $tables = [$tables];
        }

        foreach ($tables as $table) {
            $result += DB::connection('mysql')->table($table)->count();
        }

        return $result;
    }

    /**
     * @todo ip_invalid zapisac do mongo
     * @todo Co z kolumna flood?
     */
    private function migrateUsers()
    {
        $this->info('Users...');

        $sql = DB::connection('mysql')->table('user')->where('user_id', '>', 0)->orderBy('user_id')->get();
        $bar = $this->output->createProgressBar(count($sql));

        DB::beginTransaction();

        try {
            foreach ($sql as $row) {
                $row = $this->skipPrefix('user_', $row);

                unset($row['permission'], $row['ip_invalid'], $row['submit_enter']);
                $this->rename($row, 'regdate', 'created_at');
                $this->rename($row, 'dateformat', 'date_format');
                $this->rename($row, 'lastvisit', 'visited_at');
                $this->rename($row, 'ip_login', 'browser');
                $this->rename($row, 'ip_access', 'access_ip');
                $this->rename($row, 'alert_access', 'alert_failure');
                $this->rename($row, 'notify', 'alerts');
                $this->rename($row, 'notify_unread', 'alerts_unread');
                $this->rename($row, 'allow_notify', 'allow_subscribe');
                $this->rename($row, 'active', 'is_active');
                $this->rename($row, 'confirm', 'is_confirm');
                $this->rename($row, 'group', 'group_id');
                $this->rename($row, 'post', 'posts');

                $this->timestampToDatetime($row['created_at']);

                if ($row['visited_at']) {
                    $this->timestampToDatetime($row['visited_at']);
                } else {
                    $row['visited_at'] = null;
                }

                $this->setNullIfEmpty($row['photo']);
                $row['updated_at'] = $row['visited_at'] ?: $row['created_at'];

                DB::table('users')->insert($row);

                $bar->advance();
            }

            DB::commit();
            $bar->finish();
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error($e->getMessage());
        }

        $this->line('');
        $this->info('Done');
    }

    /**
     * @100%
     */
    public function migrateGroups()
    {
        $this->info('Groups...');
        $groups = DB::connection('mysql')->table('group')->where('group_id', '>', 2)->orderBy('group_id')->get();

        DB::beginTransaction();

        try {
            foreach ($groups as $group) {
                $group = $this->skipPrefix('group_', $group);

                unset($group['display'], $group['open'], $group['type']);
                $this->rename($group, 'desc', 'description');
                $this->rename($group, 'leader', 'leader_id');
                $this->rename($group, 'exposed', 'partner');

                $group['created_at'] = $group['updated_at'] = date('Y-m-d H:i:s');
                $this->setNullIfEmpty($group['leader_id']);

                DB::table('groups')->insert($group);

                $sql = DB::connection('mysql')->table('auth_group')->where('group_id', '=', $group['id'])->get();

                foreach ($sql as $row) {
                    DB::table('group_users')->insert((array) $row);
                }

                $sql = DB::connection('mysql')->table('auth_data')->where('data_group', '=', $group['id'])->get();

                foreach ($sql as $row) {
                    $row = (array) $row;

                    $this->rename($row, 'data_group', 'group_id');
                    $this->rename($row, 'data_option', 'permission_id');
                    $this->rename($row, 'data_value', 'value');

                    DB::table('group_permissions')->insert($row);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error($e->getMessage());
        }

        $this->line('');
        $this->info('Done');
    }

    /**
     * 100%
     */
    private function migratePermissions()
    {
        $this->info('Permissions...');
        $permissions = DB::connection('mysql')->table('auth_option')->get();

        DB::beginTransaction();

        try {
            foreach ($permissions as $permission) {
                $permission = $this->skipPrefix('option_', $permission);

                $this->rename($permission, 'text', 'name');
                $this->rename($permission, 'label', 'description');

                $mapping = [
                    'a_' => 'adm-access',
                    'f_sticky' => 'forum-sticky',
                    'f_edit' => 'forum-update',
                    'f_delete' => 'forum-delete',
                    'f_announcement' => 'forum-announcement',
                    'f_lock' => 'forum-lock',
                    'f_move' => 'forum-move',
                    'f_merge' => 'forum-merge',
                    'm_edit' => 'microblog-update',
                    'm_delete' => 'microblog-delete',
                ];

                if (in_array($permission['name'], array_keys($mapping))) {
                    $permission['name'] = str_replace(array_keys($mapping), array_values($mapping), $permission['name']);
                    DB::table('permissions')->insert($permission);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error($e->getMessage());
        }

        $this->line('');
        $this->info('Done');
    }

    /**
     * 100%
     */
    private function migrateSkills()
    {
        $this->info('User skills...');

        $sql = DB::connection('mysql')->table('user_skill')->get();
        $bar = $this->output->createProgressBar(count($sql));

        DB::beginTransaction();

        try {
            foreach ($sql as $row) {
                $row = $this->skipPrefix('skill_', $row);

                $this->rename($row, 'user', 'user_id');
                DB::table('user_skills')->insert($row);

                $bar->advance();
            }

            DB::commit();
            $bar->finish();
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error($e->getMessage());
        }

        $this->line('');
        $this->info('Done');
    }

    /**
     * @todo Slowa zawieraja niewspierane znaczniki <ort> Trzeba to usunac z tekstu
     */
    private function migrateWords()
    {
        $this->info('Words...');

        $sql = DB::connection('mysql')->table('censore')->get();
        $bar = $this->output->createProgressBar(count($sql));

        DB::beginTransaction();

        try {
            foreach ($sql as $row) {
                $row = $this->skipPrefix('censore_', $row);

                $this->rename($row, 'text', 'word');
                DB::table('words')->insert($row);

                $bar->advance();
            }

            DB::commit();
            $bar->finish();
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error($e->getMessage());
        }

        $this->line('');
        $this->info('Done');
    }

    /**
     * Wymaga uzupelnienia tabeli alert_types
     *
     * @todo Co z subdomena forum? Jezeli nie zapisujemy hostow trzeba prowadic do prawidlowego aresu url
     */
    private function migrateAlerts()
    {
        $this->info('Alerts...');
        $count = $this->count(['notify_header', 'notify_sender', 'notify_user']);

        $bar = $this->output->createProgressBar($count);

        DB::beginTransaction();

        try {
            DB::statement('TRUNCATE alert_settings');

            $sql = DB::connection('mysql')
                ->table('notify_header')
                ->select(['notify_header.*', 'notify_sender.sender_time AS header_sender_time'])
                ->join('notify_sender', 'sender_header', '=', 'header_id')
                ->groupBy('header_id')
                ->get();

            foreach ($sql as $row) {
                $row = $this->skipPrefix('header_', $row);

                $this->rename($row, 'notify', 'type_id');
                $this->rename($row, 'recipient', 'user_id');
                $this->rename($row, 'sender_time', 'created_at');
                $this->rename($row, 'read', 'read_at');
                $this->rename($row, 'mark', 'is_marked');

                unset($row['time'], $row['sender'], $row['headline']);

                $this->timestampToDatetime($row['created_at']);
                $row['object_id'] = substr(md5($row['type_id'] . $row['subject']), 16);

                $this->setNullIfEmpty($row['url']);
                $this->setNullIfEmpty($row['excerpt']);

                $row['read_at'] = !$row['read_at'] ? null : $this->timestampToDatetime($row['read_at']);

                if (empty($row['subject'])) {
                    $row['subject'] = '';
                }

                if ($row['url']) {
                    $row['url'] = $this->skipHost($row['url']);
                }

                DB::table('alerts')->insert($row);
                $bar->advance();
            }

            ///////////////////////////////////////////////////////////////////////////////

            DB::connection('mysql')->table('notify_sender')->chunk(100000, function ($sql) use ($bar) {
                foreach ($sql as $row) {
                    $row = $this->skipPrefix('sender_', (array) $row);

                    $this->rename($row, 'user', 'user_id');
                    $this->rename($row, 'time', 'created_at');
                    $this->rename($row, 'header', 'alert_id');

                    $this->timestampToDatetime($row['created_at']);
                    DB::table('alert_senders')->insert($row);

                    $bar->advance();
                }
            });

            //////////////////////////////////////////////////////////////////////////////////

            DB::connection('mysql')->table('notify_user')->chunk(100000, function ($sql) use ($bar) {
                foreach ($sql as $row) {
                    $row = (array) $row;

                    DB::table('alert_settings')->insert([
                        'type_id' => $row['notify_id'],
                        'user_id' => $row['user_id'],
                        'profile' => $row['notifier'] & 1,
                        'email' => $row['notifier'] & 2
                    ]);

                    $bar->advance();
                }
            });

            $bar->finish();
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            $this->error($e->getFile() . ' [' . $e->getLine() . ']: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }

        $this->line('');
        $this->info('Done');
    }

    /**
     * 90% (oprocz samej tresci wiadomosci - zmiana parsera)
     * W poprzedniej wersji nie bylo grupowania po polu "root"? :/
     */
    private function migratePm()
    {
        $this->info('Pms...');
        $count = $this->count(['pm', 'pm_text']);

        $bar = $this->output->createProgressBar($count);

        DB::beginTransaction();

        try {
            $sql = DB::connection('mysql')
                ->table('pm_text')
                ->select(['pm_text.*', 'pm.pm_time AS pm_time'])
                ->join('pm', 'pm.pm_text', '=', 'pm_text.pm_text')
                ->groupBy('pm_text')
                ->get();

            foreach ($sql as $row) {
                $row = $this->skipPrefix('pm_', (array) $row);

                $this->rename($row, 'text', 'id');
                $this->rename($row, 'message', 'text');
                $this->rename($row, 'time', 'created_at');

                $this->timestampToDatetime($row['created_at']);

                DB::table('pm_text')->insert($row);
                $bar->advance();
            }

            ///////////////////////////////////////////////////////////////////////////////

            $sql = DB::connection('mysql')->table('pm')->get();

            foreach ($sql as $row) {
                $row = $this->skipPrefix('pm_', (array) $row);

                $from = $row['from'];
                $to = $row['to'];

                $this->rename($row, 'read', 'read_at');
                $this->rename($row, 'trunk', 'root_id');
                $this->rename($row, 'text', 'text_id');

                if ($row['read_at'] == 1) {
                    $row['read_at'] = $row['time'];
                }

                if ($row['read_at']) {
                    $this->timestampToDatetime($row['read_at']);
                } else {
                    $row['read_at'] = null;
                }

                if ($row['folder'] == Pm::INBOX) {
                    $row['user_id'] = $to;
                    $row['author_id'] = $from;
                } else {
                    $row['user_id'] = $from;
                    $row['author_id'] = $to;
                }

                $row['root_id'] = $row['user_id'] + $row['author_id'];

                unset($row['type'], $row['subject'], $row['time'], $row['from'], $row['to']);

                DB::table('pm')->insert($row);

                $bar->advance();
            }

            $bar->finish();
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            $this->error($e->getFile() . ' [' . $e->getLine() . ']: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }

        $this->line('');
        $this->info('Done');
    }

    /**
     *  WYMAGA DODANIA DANYCH DO TABELI REPUTATION_TYPES
     * 100%
     */
    public function migrateReputation()
    {
        $this->info('Reputations...');

        DB::beginTransaction();

        try {
            $sql = DB::connection('mysql')
                    ->table('reputation_activity')
                    ->select(['reputation_activity.*', 'module_name AS activity_module_name'])
                    ->leftJoin('page', 'page_id', '=', 'activity_page')
                    ->leftJoin('module', 'module_id', '=', 'page_module')
                    ->get();

            $bar = $this->output->createProgressBar(count($sql));

            foreach ($sql as $row) {
                $row = $this->skipPrefix('activity_', (array) $row);

                $this->rename($row, 'reputation', 'type_id');
                $this->rename($row, 'user', 'user_id');
                $this->rename($row, 'time', 'created_at');
                $this->rename($row, 'subject', 'excerpt');

                $this->timestampToDatetime($row['created_at']);
                $metadata = [];

                if ($row['url']) {
                    $row['url'] = $this->skipHost($row['url']);
                }

                if (empty($row['module_name'])) {
                    $metadata['microblog_id'] = $row['item'];
                } elseif ($row['module_name'] == 'forum') {
                    $metadata['post_id'] = $row['item'];
                }

                $row['metadata'] = json_encode($metadata);
                unset($row['enable'], $row['page'], $row['item'], $row['module_name']);

                $row['excerpt'] = str_limit($row['excerpt'], 250);
                $row['url'] = str_limit($row['url'], 250);

                DB::table('reputations')->insert($row);
                $bar->advance();
            }

            $bar->finish();
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            $this->error($e->getFile() . ' [' . $e->getLine() . ']: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }

        $this->line('');
        $this->info('Done');
    }

    private function migrateForum()
    {
        $this->info('Forum...');

        DB::beginTransaction();

        try {
            $sql = DB::connection('mysql')
                ->table('forum')
                ->select([
                    'forum.*',
                    'page_subject AS forum_name',
                    'page_title AS forum_title',
                    'page_path AS forum_path',
                    'page_order AS forum_order',
                    'page_depth AS forum_depth'
                ])
                ->leftJoin('page', 'page_id', '=', 'forum_page')
                ->orderBy('page_matrix')
                ->get();

            $parentId = null;
            $groups = [];

            foreach ($sql as $row) {
                $row = $this->skipPrefix('forum_', (array) $row);

                $this->rename($row, 'lock', 'is_locked');
                $this->rename($row, 'prune', 'prune_days');

                if ($row['depth'] == 1) {
                    $parentId = $row['id'];
                } else {
                    $row['parent_id'] = $parentId;
                }

                $row['enable_prune'] = $row['prune_days'] > 0;
                $groups[$row['page']] = $row['id'];

                $permissions = unserialize($row['permission']);

                unset($row['depth'], $row['permission'], $row['page']);

                DB::table('forums')->insert($row);

                foreach ($permissions as $groupId => $rowset) {
                    if ($groupId > 2) {
                        foreach ($rowset as $permissionId => $value) {
                            DB::table('forum_permissions')->insert([
                                'forum_id' => $row['id'],
                                'group_id' => $groupId,
                                'permission_id' => $permissionId,
                                'value' => $value
                            ]);
                        }
                    }
                }
            }

            $sql = DB::connection('mysql')->table('page_group')->whereIn('page_id', array_keys($groups))->get();

            foreach ($sql as $row) {
                if ($row->group_id > 2) {
                    DB::table('forum_access')->insert(['forum_id' => $groups[$row->page_id], 'group_id' => $row->group_id]);
                }
            }

            $sql = DB::connection('mysql')->table('forum_marking')->get();
            $bar = $this->output->createProgressBar(count($sql));

            foreach ($sql as $row) {
                $row = (array) $row;

                $this->rename($row, 'mark_time', 'marked_at');
                $this->timestampToDatetime($row['marked_at']);

                DB::table('forum_track')->insert($row);
                $bar->advance();
            }

            $bar->finish();
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            $this->error($e->getFile() . ' [' . $e->getLine() . ']: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }

        $this->line('');
        $this->info('Done');
    }

    public function migrateTopic()
    {
        $this->info('Topic...');

        $count = $this->count(['topic', 'topic_marking', 'topic_user']);
        $bar = $this->output->createProgressBar($count);

        DB::beginTransaction();

        try {
            DB::connection('mysql')
                ->table('topic')
                ->select([
                    'topic.*',
                    'page_subject AS topic_subject',
                    'page_path AS topic_path',
                    'p1.post_time AS topic_created_at',
                    'p2.post_time AS topic_updated_at',
                ])
                ->leftJoin('page', 'page_id', '=', 'topic_page')
                ->join('post AS p1', 'p1.post_id', '=', 'topic_first_post_id')
                ->join('post AS p2', 'p2.post_id', '=', 'topic_first_post_id')
                ->orderBy('topic_id')
                ->chunk(100000, function ($sql) use ($bar) {

                    foreach ($sql as $row) {
                        $row = $this->skipPrefix('topic_', (array)$row);

                        $this->rename($row, 'forum', 'forum_id');
                        $this->rename($row, 'vote', 'score');
                        $this->rename($row, 'sticky', 'is_sticky');
                        $this->rename($row, 'announcement', 'is_announcement');
                        $this->rename($row, 'lock', 'is_locked');
                        $this->rename($row, 'poll', 'poll_id');
                        $this->rename($row, 'moved_id', 'prev_forum_id');
                        $this->rename($row, 'last_post_time', 'last_post_created_at');

                        $this->timestampToDatetime($row['last_post_created_at']);
                        $this->timestampToDatetime($row['created_at']);
                        $this->timestampToDatetime($row['updated_at']);

                        if ($row['delete']) {
                            $row['deleted_at'] = $row['updated_at'];
                        } else {
                            $row['deleted_at'] = null;
                        }

                        unset($row['solved'], $row['page'], $row['delete']);

                        DB::table('topics')->insert($row);
                        $bar->advance();
                    }
                });

            DB::connection('mysql')->table('topic_marking')->chunk(100000, function ($sql) use ($bar) {
                foreach ($sql as $row) {
                    $row = (array) $row;

                    $this->rename($row, 'mark_time', 'marked_at');
                    $this->timestampToDatetime($row['marked_at']);

                    DB::table('topic_track')->insert($row);
                    $bar->advance();
                }
            });

            DB::connection('mysql')->table('topic_user')->chunk(100000, function ($sql) use ($bar) {
                foreach ($sql as $row) {
                    DB::table('topic_users')->insert((array) $row);
                    $bar->advance();
                }
            });

            $bar->finish();
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            $this->error($e->getFile() . ' [' . $e->getLine() . ']: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }

        $this->line('');
        $this->info('Done');
    }

    /**
     * @todo Usuniecie posta trzeba przepisac do mongo
     */
    public function migratePost()
    {
        $this->info('Post...');

        $count = $this->count(['post']);
        $bar = $this->output->createProgressBar($count);

        DB::beginTransaction();

        try {
            DB::connection('mysql')
                ->table('post')
                ->select(['post.*', 'text_content AS post_content'])
                ->join('post_text', 'text_id', '=', 'post_text')
                ->chunk(50000, function ($sql) use ($bar) {

                    foreach ($sql as $row) {
                        $row = $this->skipPrefix('post_', (array)$row);

                        $this->rename($row, 'forum', 'forum_id');
                        $this->rename($row, 'topic', 'topic_id');
                        $this->rename($row, 'user', 'user_id');
                        $this->rename($row, 'username', 'user_name');
                        $this->rename($row, 'time', 'created_at');
                        $this->rename($row, 'edit_time', 'updated_at');
                        $this->rename($row, 'edit_user', 'editor_id');
                        $this->rename($row, 'vote', 'score');
                        $this->rename($row, 'content', 'text');

                        $this->timestampToDatetime($row['created_at']);
                        $this->timestampToDatetime($row['updated_at']);

                        if ($row['delete']) {
                            $row['deleted_at'] = $this->timestampToDatetime($row['delete_time']);
                        } else {
                            $row['deleted_at'] = null;
                        }

                        unset($row['enable_smilies'], $row['enable_html'], $row['delete'], $row['delete_user'], $row['delete_time']);

                        DB::table('posts')->insert($row);
                        $bar->advance();
                    }
                });

//            DB::connection('mysql')->table('topic_marking')->chunk(100000, function ($sql) use ($bar) {
//                foreach ($sql as $row) {
//                    $row = (array) $row;
//
//                    $this->rename($row, 'mark_time', 'marked_at');
//                    $this->timestampToDatetime($row['marked_at']);
//
//                    DB::table('topic_track')->insert($row);
//                    $bar->advance();
//                }
//            });
//
//            DB::connection('mysql')->table('topic_user')->chunk(100000, function ($sql) use ($bar) {
//                foreach ($sql as $row) {
//                    DB::table('topic_user')->insert((array) $row);
//                    $bar->advance();
//                }
//            });

            $bar->finish();
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            $this->error($e->getFile() . ' [' . $e->getLine() . ']: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }

        $this->line('');
        $this->info('Done');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        DB::statement('SET session_replication_role = replica');
//        $this->migrateUsers();
        /* musi byc przed dodawaniem grup */
//        $this->migratePermissions();
//        $this->migrateGroups();
//        $this->migrateSkills();
//        $this->migrateWords();
//        $this->migrateAlerts();
//        $this->migratePm();
//        $this->migrateReputation();
//        $this->migrateForum();
//        $this->migrateTopic();
        $this->migratePost();

        DB::statement('SET session_replication_role = DEFAULT');
    }
}