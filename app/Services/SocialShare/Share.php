<?PHP

namespace App\Services\SocialShare;

use Config;

//use App\Repositories\RepositoryInterface;


class Share
{

    public function getArtistData($content_id, $artist_id)
    {
        $result = [];

        $content = \App\Models\Content::where('_id', $content_id)->first();
        $artist = \App\Models\Artistconfig::where('artist_id', $artist_id)->first();

        $result['content'] = $content;
        $result['artist'] = $artist;

        return $result;
    }
}