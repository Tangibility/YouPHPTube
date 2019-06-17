<?php
global $global, $config, $isChannel;
$isChannel = 1; // still workaround, for gallery-functions, please let it there.
if (!isset($global['systemRootPath'])) {
    require_once '../videos/configuration.php';
}
require_once $global['systemRootPath'] . 'objects/user.php';
require_once $global['systemRootPath'] . 'objects/video.php';
require_once $global['systemRootPath'] . 'objects/playlist.php';

if (empty($_GET['channelName'])) {
    if (User::isLogged()) {
        $_GET['user_id'] = User::getId();
    } else {
        return false;
    }
} else {
    $user = User::getChannelOwner($_GET['channelName']);
    if (!empty($user)) {
        $_GET['user_id'] = $user['id'];
    } else {
        $_GET['user_id'] = $_GET['channelName'];
    }
}
$user_id = $_GET['user_id'];

$publicOnly = true;
$isMyChannel = false;
if (User::isLogged() && $user_id == User::getId()) {
    $publicOnly = false;
    $isMyChannel = true;
}

$playlists = PlayList::getAllFromUser($user_id, $publicOnly);
?>

<?php
$channelName = @$_GET['channelName'];
unset($_GET['channelName']);
$startC = microtime(true);
foreach ($playlists as $playlist) {
    @$timesC[__LINE__] += microtime(true) - $startC;
    $startC = microtime(true);
    $videosArrayId = PlayList::getVideosIdFromPlaylist($playlist['id']);
    @$timesC[__LINE__] += microtime(true) - $startC;
    $startC = microtime(true);
    if(empty($videosArrayId)){
        $videosP = array();
    }else if ($advancedCustom->AsyncJobs) {
        $videosP = Video::getAllVideosAsync("viewable", false, true, $videosArrayId);
    } else {
        $videosP = Video::getAllVideos("viewable", false, true, $videosArrayId);
    }
    @$timesC[__LINE__] += microtime(true) - $startC;
    $startC = microtime(true);
    //error_log("channelPlaylist videosP: ".json_encode($videosP));
    $videosP = PlayList::sortVideos($videosP, $videosArrayId);
    @$timesC[__LINE__] += microtime(true) - $startC;
    $startC = microtime(true);
    //error_log("channelPlaylist videosP2: ".json_encode($videosP));
    //error_log("channelPlaylist videosArrayId: ".json_encode($videosArrayId));
    $playListButtons = YouPHPTubePlugin::getPlayListButtons($playlist['id']);
    @$timesC[__LINE__] += microtime(true) - $startC;
    $startC = microtime(true);
    ?>

    <div class="panel panel-default" playListId="<?php echo $playlist['id']; ?>">
        <div class="panel-heading">

            <strong style="font-size: 1.1em;" class="playlistName"><?php echo $playlist['name']; ?> </strong>

            <?php
            if (!empty($videosArrayId)) {
                ?>
                <a href="<?php echo $global['webSiteRootURL']; ?>playlist/<?php echo $playlist['id']; ?>" class="btn btn-xs btn-default playAll hrefLink" ><span class="fa fa-play"></span> <?php echo __("Play All"); ?></a><?php echo $playListButtons; ?>
                <?php
            }
            if ($isMyChannel) {
                ?>
                <script>
                    $(function () {
                        $("#sortable<?php echo $playlist['id']; ?>").sortable({
                            stop: function (event, ui) {
                                modal.showPleaseWait();
                                saveSortable(this, <?php echo $playlist['id']; ?>);
                            }
                        });
                        $("#sortable<?php echo $playlist['id']; ?>").disableSelection();
                    });
                </script>
                <div class="pull-right btn-group">

                    <?php
                    if (!empty($videosArrayId)) {
                        ?>
                        <button class="btn btn-xs btn-info" ><i class="fa fa-info-circle"></i> <?php echo __("Drag and drop to sort"); ?></button>

                        <?php
                    }
                    if ($playlist['status'] != "favorite" && $playlist['status'] != "watch_later") {
                        if (YouPHPTubePlugin::isEnabledByName("PlayLists")) {
                            ?>
                            <button class="btn btn-xs btn-default" onclick="copyToClipboard($('#playListEmbedCode<?php echo $playlist['id']; ?>').val());setTextEmbedCopied();" ><span class="fa fa-copy"></span> <span id="btnEmbedText"><?php echo __("Copy embed code"); ?></span></button>
                            <input type="hidden" id="playListEmbedCode<?php echo $playlist['id']; ?>" value='<iframe width="640" height="480" style="max-width: 100%;max-height: 100%;" src="<?php echo $global['webSiteRootURL']; ?>plugin/PlayLists/embed.php?playlists_id=<?php echo $playlist['id']; ?>" frameborder="0" allowfullscreen="allowfullscreen" allow="autoplay"></iframe>'/>
                            <?php
                        }
                        ?>
                        <button class="btn btn-xs btn-danger deletePlaylist" playlist_id="<?php echo $playlist['id']; ?>" ><span class="fa fa-trash-o"></span> <?php echo __("Delete"); ?></button>
                        <button class="btn btn-xs btn-primary renamePlaylist" playlist_id="<?php echo $playlist['id']; ?>" ><span class="fa fa-pencil"></span> <?php echo __("Rename"); ?></button>
                        <button class="btn btn-xs btn-default statusPlaylist" playlist_id="<?php echo $playlist['id']; ?>" style="" >
                            <span class="fa fa-lock" id="statusPrivate" style="color: red; <?php
                            if ($playlist['status'] !== 'private') {
                                echo ' display: none;';
                            }
                            ?> " ></span> 
                            <span class="fa fa-globe" id="statusPublic" style="color: green; <?php
                            if ($playlist['status'] !== 'public') {
                                echo ' display: none;';
                            }
                            ?>"></span> 
                            <span class="fa fa-eye-slash" id="statusUnlisted" style="color: gray;   <?php
                            if ($playlist['status'] !== 'unlisted') {
                                echo ' display: none;';
                            }
                            ?>"></span>
                        </button>
                        <?php
                    }
                    ?>
                </div>
                <?php
            }
            ?>
        </div>

        <?php
        if (!empty($videosArrayId)) {
            ?>

            <div class="panel-body">

                <div id="sortable<?php echo $playlist['id']; ?>" style="list-style: none;">
                    <?php
                    $count = 0;
                    foreach ($videosP as $value) {
                        $count++;
                        $img_portrait = ($value['rotation'] === "90" || $value['rotation'] === "270") ? "img-portrait" : "";
                        $name = User::getNameIdentificationById($value['users_id']);

                        $images = Video::getImageFromFilename($value['filename'], $value['type'], true);
                        $imgGif = $images->thumbsGif;
                        $poster = $images->thumbsJpg;
                        $class = "";
                        $style = "";
                        if($count>6){
                            $class = "showMoreLess{$playlist['id']}";
                            $style = "display: none;";
                        }
                        ?>
                        <li class="col-lg-2 col-md-4 col-sm-4 col-xs-6 galleryVideo <?php echo $class; ?> " id="<?php echo $value['id']; ?>" style="padding: 1px;  <?php echo $style; ?>">
                            <div class="panel panel-default" playListId="<?php echo $playlist['id']; ?>" style="min-height: 208px;">
                                <div class="panel-body" style="overflow: hidden;">

                                    <a class="aspectRatio16_9" href="<?php echo $global['webSiteRootURL']; ?>video/<?php echo $value['clean_title']; ?>" title="<?php echo $value['title']; ?>" style="margin: 0;" >
                                        <img src="<?php echo $poster; ?>" alt="<?php echo $value['title']; ?>" class="img img-responsive <?php echo $img_portrait; ?>  rotate<?php echo $value['rotation']; ?>" />
                                        <span class="duration"><?php echo Video::getCleanDuration($value['duration']); ?></span>
                                    </a>
                                    <a class="hrefLink" href="<?php echo $global['webSiteRootURL']; ?>video/<?php echo $value['clean_title']; ?>" title="<?php echo $value['title']; ?>">
                                        <h2><?php echo $value['title']; ?></h2>
                                    </a>
                                    <div class="text-muted galeryDetails" style="min-height: 60px;">
                                        <div>
                                            <?php
                                            $value['tags'] = Video::getTags($value['id']);
                                            foreach ($value['tags'] as $value2) {
                                                if ($value2->label === __("Group")) {
                                                    ?>
                                                    <span class="label label-<?php echo $value2->type; ?>"><?php echo $value2->text; ?></span>
                                                    <?php
                                                }
                                            }
                                            ?>
                                        </div>
                                        <?php
                                        if (empty($advancedCustom->doNotDisplayViews)) {
                                            ?> 
                                            <div>
                                                <i class="fa fa-eye"></i>
                                                <span itemprop="interactionCount">
                                                    <?php echo number_format($value['views_count'], 0); ?> <?php echo __("Views"); ?>
                                                </span>
                                            </div>
                                            <?php
                                        }
                                        ?>

                                        <div>
                                            <i class="fa fa-clock-o"></i>
                                            <?php
                                            echo humanTiming(strtotime($value['videoCreation'])), " ", __('ago');
                                            ?>
                                        </div>
                                        <div>
                                            <i class="fa fa-user"></i>
                                            <?php
                                            echo $name;
                                            ?>
                                        </div>
                                        <?php
                                        if (Video::canEdit($value['id'])) {
                                            ?>
                                            <div>
                                                <a href="<?php echo $global['webSiteRootURL']; ?>mvideos?video_id=<?php echo $value['id']; ?>" class="text-primary"><i class="fa fa-edit"></i> <?php echo __("Edit Video"); ?></a>


                                            </div>
                                            <?php
                                        }
                                        ?>
                                        <?php
                                        if ($isMyChannel) {
                                            ?>
                                            <div>
                                                <span style=" cursor: pointer;" class="btn-link text-primary removeVideo" playlist_id="<?php echo $playlist['id']; ?>" video_id="<?php echo $value['id']; ?>">
                                                    <i class="fa fa-trash"></i> <?php echo __("Remove"); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span class="text-primary" playlist_id="<?php echo $playlist['id']; ?>" video_id="<?php echo $value['id']; ?>">
                                                    <i class="fas fa-sort-numeric-down"></i> <?php echo __("Sort"); ?> 
                                                    <input type="number" step="1" class="video_order" value="<?php echo intval($playlist['videos'][$count - 1]['video_order']); ?>" style="max-width: 50px;">
                                                    <button class="btn btn-sm btn-xs sortNow"><i class="fas fa-check-square"></i></button>
                                                </span>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <div class="panel-footer">
                <button class="btn btn-default btn-xs btn-sm showMoreLessBtn<?php echo $playlist['id']; ?>" onclick="$('.showMoreLessBtn<?php echo $playlist['id']; ?>').toggle();$('.<?php echo $class; ?>').slideDown();"><i class="fas fa-angle-down"></i> <?php echo __('Show More'); ?></button>
                <button class="btn btn-default btn-xs btn-sm  showMoreLessBtn<?php echo $playlist['id']; ?>" onclick="$('.showMoreLessBtn<?php echo $playlist['id']; ?>').toggle();$('.<?php echo $class; ?>').slideUp();" style="display: none;"><i class="fas fa-angle-up"></i> <?php echo __('Show Less'); ?></button>
            </div>  
            <?php
        }
        ?>

    </div>
    <?php
}

$_GET['channelName'] = $channelName;
?>
<script>

    var timoutembed;
    function setTextEmbedCopied() {
        clearTimeout(timoutembed);
        $("#btnEmbedText").html("<?php echo __("Copied!"); ?>");
        setTimeout(function () {
            $("#btnEmbedText").html("<?php echo __("Copy embed code"); ?>");
        }, 3000);
    }

    function saveSortable($sortableObject, playlist_id) {
        var list = $($sortableObject).sortable("toArray");
        $.ajax({
            url: '<?php echo $global['webSiteRootURL']; ?>objects/playlistSort.php',
            data: {
                "list": list,
                "playlist_id": playlist_id
            },
            type: 'post',
            success: function (response) {
                $("#channelPlaylists").load(webSiteRootURL + "view/channelPlaylist.php?channelName=" + channelName);
                modal.hidePleaseWait();
            }
        });
    }

    function sortNow($t, position) {
        var $this = $($t).closest('.galleryVideo');
        var $uiDiv = $($t).closest('.ui-sortable');
        var $playListId = $($t).closest('.panel').attr('playListId');
        var $list = $($t).closest('.ui-sortable').find('li');
        if (position < 0) {
            return false;
        }
        if (position === 0) {
            $this.slideUp(500, function () {
                $this.insertBefore($this.siblings(':eq(0)'));
                saveSortable($uiDiv, $playListId);
            }).slideDown(500);
        } else if ($list.length - 1 > position) {
            $this.slideUp(500, function () {
                $this.insertBefore($this.siblings(':eq(' + position + ')'));
                saveSortable($uiDiv, $playListId);
            }).slideDown(500);
        } else {
            $this.slideUp(500, function () {
                $this.insertAfter($this.siblings(':eq(' + ($list.length - 2) + ')'));
                saveSortable($uiDiv, $playListId);
            }).slideDown(500);
        }
    }

    var currentObject;
    $(function () {
        $('.removeVideo').click(function () {
            currentObject = this;
            swal({
                title: "<?php echo __("Are you sure?"); ?>",
                text: "<?php echo __("You will not be able to recover this action!"); ?>",
                type: "warning",
                showCancelButton: true,
                confirmButtonColor: "#DD6B55",
                confirmButtonText: "<?php echo __("Yes, delete it!"); ?>",
                closeOnConfirm: true
            },
                    function () {
                        modal.showPleaseWait();
                        var playlist_id = $(currentObject).attr('playlist_id');
                        var video_id = $(currentObject).attr('video_id');
                        $.ajax({
                            url: '<?php echo $global['webSiteRootURL']; ?>objects/playlistRemoveVideo.php',
                            data: {
                                "playlist_id": playlist_id,
                                "video_id": video_id
                            },
                            type: 'post',
                            success: function (response) {
                                reloadPlayLists();
                                $(".playListsIds" + video_id).prop("checked", false);
                                $(currentObject).closest('.galleryVideo').fadeOut();
                                modal.hidePleaseWait();
                            }
                        });
                    });
        });

        $('.deletePlaylist').click(function () {
            currentObject = this;
            swal({
                title: "<?php echo __("Are you sure?"); ?>",
                text: "<?php echo __("You will not be able to recover this action!"); ?>",
                type: "warning",
                showCancelButton: true,
                confirmButtonColor: "#DD6B55",
                confirmButtonText: "<?php echo __("Yes, delete it!"); ?>",
                closeOnConfirm: true
            },
                    function () {
                        modal.showPleaseWait();
                        var playlist_id = $(currentObject).attr('playlist_id');
                        console.log(playlist_id);
                        $.ajax({
                            url: '<?php echo $global['webSiteRootURL']; ?>objects/playlistRemove.php',
                            data: {
                                "playlist_id": playlist_id
                            },
                            type: 'post',
                            success: function (response) {
                                $(currentObject).closest('.panel').slideUp();
                                modal.hidePleaseWait();
                            }
                        });
                    });

        });

        $('.statusPlaylist').click(function () {
            status = "public";
            if ($('#statusPrivate').is(":visible")) {
                status = "public";
                $('.statusPlaylist span').hide();
                $('#statusPublic').fadeIn();
            } else if ($('#statusPublic').is(":visible")) {
                status = "unlisted";
                $('.statusPlaylist span').hide();
                $('#statusUnlisted').fadeIn();
            } else if ($('#statusUnlisted').is(":visible")) {
                status = "private";
                $('.statusPlaylist span').hide();
                $('#statusPrivate').fadeIn();
            }
            modal.showPleaseWait();
            var playlist_id = $(this).attr('playlist_id');
            console.log(playlist_id);
            $.ajax({
                url: '<?php echo $global['webSiteRootURL']; ?>objects/playlistStatus.php',
                data: {
                    "playlist_id": playlist_id,
                    "status": status
                },
                type: 'post',
                success: function (response) {

                    modal.hidePleaseWait();
                }
            });

        });

        $('.renamePlaylist').click(function () {
            currentObject = this;
            swal({
                title: "<?php echo __("Change Playlist Name"); ?>!",
                text: "<?php echo __("What is the new name?"); ?>",
                type: "input",
                showCancelButton: true,
                closeOnConfirm: true,
                inputPlaceholder: "<?php echo __("Playlist name?"); ?>"
            },
                    function (inputValue) {
                        if (inputValue === false)
                            return false;

                        if (inputValue === "") {
                            swal.showInputError("<?php echo __("You need to tell us the new name?"); ?>");
                            return false
                        }

                        modal.showPleaseWait();
                        var playlist_id = $(currentObject).attr('playlist_id');
                        console.log(playlist_id);
                        $.ajax({
                            url: '<?php echo $global['webSiteRootURL']; ?>objects/playlistRename.php',
                            data: {
                                "playlist_id": playlist_id,
                                "name": inputValue
                            },
                            type: 'post',
                            success: function (response) {
                                $(currentObject).closest('.panel').find('.playlistName').text(inputValue);
                                modal.hidePleaseWait();
                            }
                        });
                        return false;
                    });

        });

        $('.sortNow').click(function () {
            var $val = $(this).siblings("input").val();
            sortNow(this, $val);
        });

        $('.video_order').keypress(function (e) {
            if (e.which == 13) {
                sortNow(this, $(this).val());
            }
        });

    });
</script>
<!--
channelPlaylist
<?php
$timesC[__LINE__] = microtime(true) - $startC;
$startC = microtime(true);
foreach ($timesC as $key => $value) {
    echo "Line: {$key} -> {$value}\n";
}
?>
-->