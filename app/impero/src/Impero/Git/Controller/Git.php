<?php namespace Impero\Git\Controller;

use Impero\Apache\Record\Site;
use Pckg\Framework\Controller;

class Git extends Controller
{

    public function postWebhookAction(Site $site)
    {
        $data = json_decode(file_get_contents("php://input"));

        foreach ($data->push->changes as $change) {
            if (!isset($change->old->type) || !isset($change->old->name)) {
                continue;
            }

            if ($change->old->type == 'branch' && $change->old->name == 'preprod') {
                queue()->create('git:pull', [
                    'dir' => $site->getHtdocsPath(),
                ]);

                break;
            }
        }
    }

}