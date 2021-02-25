<?php
namespace App\Services\Amazon;

use \Aws\MediaConvert\MediaConvertClient;
use \Aws\Exception\AwsException;

use Config, Log;
use Carbon;

class Mediaconvert {


    protected $mediaConvertClient;

    public function __construct()
    {
        parent::__construct();

        $ACCOUNT_ENDPOINT = "https://yz72y1um.mediaconvert.ap-southeast-1.amazonaws.com";

        $this->mediaConvertClient = new MediaConvertClient([
            'credentials' => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY')],
            'version' => '2017-08-29',
            'region' => 'ap-southeast-1',
            'endpoint' => $ACCOUNT_ENDPOINT
        ]);

    }


    public function createHlsVodJob ($params = array()){

        $error_messages = $results = [];

        try {

//          $this->sampleTranscode();

            //  Sample - s3://armsrawvideos/1532776693_tmpphp8xgz38.mp4
            $s3_input_path     =   (isset($params['s3_input_path']))  ? trim($params['s3_input_path']) : "";

            //  Sample - s3://armsvideos/testcmd/
            $s3_output_path    =   (isset($params['s3_output_path']))  ? trim($params['s3_output_path']) : "";

            if($s3_input_path != '' && $s3_output_path != ''){

                $params    =   [
                    's3_input_path'    => $s3_input_path,
                    's3_output_path'   => $s3_output_path
                ];

                $jobSettings        =   $this->getJobSetting($params);

                $create_job_result  =   $this->mediaConvertClient->createJob([
                    "Role" => "arn:aws:iam::337477240173:role/mediaconvert",
                    "Settings" => $jobSettings, //JobSettings structure
                    "Queue" => "arn:aws:mediaconvert:ap-southeast-1:337477240173:queues/Default",
                    "UserMetadata" => [
                        "Customer" => "ARMS"
                    ],
                ]);

                $results            = $create_job_result;
                $results['error']   = false;

            }else{

                $error_messages = ['error'=> true, 'message' => 's3_input_path or s3_output_path cannot be blanck'];

            }

        } catch (AwsException $e) {

            // output error message if fails
            // echo $e->getMessage(); echo "\n";
            $error_messages = ['error'=> true,  'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('MediaConvert - createHlsVodJob  : Fail ', $error_messages);

        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }





    public function getJobSetting($params){

        $s3_input_path     =   trim($params['s3_input_path']);
        $s3_output_path    =   trim($params['s3_output_path']);

        $vod_720p       =   $this->get720pSetting();
        $vod_480p       =   $this->get480pSetting();
        $vod_360p       =   $this->get360pSetting();
        $vod_270p       =   $this->get270pSetting();

        $Outputs        =   [$vod_720p, $vod_480p, $vod_360p, $vod_270p];

        //$Outputs        =   [$vod_270p];

        $OutputGroups   =   [
            [
                "CustomName" => "MediaconvertCreateHlsVodJob",
                "Name" => "Apple HLS",
                "OutputGroupSettings" => [
                    "Type" => "HLS_GROUP_SETTINGS",
                    "HlsGroupSettings" => [
                        "ManifestDurationFormat"    => "INTEGER",
                        "SegmentLength"             => 10,
                        "TimedMetadataId3Period"    => 10,
                        "CaptionLanguageSetting"    => "OMIT",
                        "Destination"               => $s3_output_path,
                        "TimedMetadataId3Frame"     => "PRIV",
                        "CodecSpecification"        => "RFC_4281",
                        "OutputSelection"           => "MANIFESTS_AND_SEGMENTS",
                        "ProgramDateTimePeriod"     => 600,
                        "MinSegmentLength"          => 0,
                        "MinFinalSegmentLength"     => 0,
                        "DirectoryStructure"        => "SINGLE_DIRECTORY",
                        "ProgramDateTime"           => "EXCLUDE",
                        "SegmentControl"            => "SEGMENTED_FILES",
                        "ManifestCompression"       => "NONE",
                        "ClientCache"               => "ENABLED",
                        "StreamInfResolution"       => "INCLUDE"
                    ]
                ],
                "Outputs" => $Outputs
            ]
        ];

        $AdAvailOffset = 0;

        $Inputs = [
            [
                "AudioSelectors" => [
                    "Audio Selector 1" => [
                        "Offset" => 0,
                        "DefaultSelection" => "NOT_DEFAULT",
                        "ProgramSelection" => 1,
                        "SelectorType" => "TRACK",
                        "Tracks" => [ 1 ]
                    ]
                ],
                "VideoSelector" => [
                    "ColorSpace" => "FOLLOW"
                ],
                "FilterEnable" => "AUTO",
                "PsiControl" => "USE_PSI",
                "FilterStrength" => 0,
                "DeblockFilter" => "DISABLED",
                "DenoiseFilter" => "DISABLED",
                "TimecodeSource" => "EMBEDDED",
                "FileInput" => $s3_input_path
            ]
        ];

        $jobSetting = [
            "OutputGroups" => $OutputGroups,
            "AdAvailOffset" => $AdAvailOffset,
            "Inputs" => $Inputs,
            "TimecodeConfig" => [
                "Source" => "EMBEDDED"
            ]
        ];


        return $jobSetting;

    }


    public function get720pSetting (){

        $setting   =   [
            "ContainerSettings"=> [
                "Container"=> "M3U8",
                "M3u8Settings"=> [
                    "AudioFramesPerPes"=> 4,
                    "PcrControl"=> "PCR_EVERY_PES_PACKET",
                    "PmtPid"=> 480,
                    "PrivateMetadataPid"=> 503,
                    "ProgramNumber"=> 1,
                    "PatInterval"=> 0,
                    "PmtInterval"=> 0,
                    "VideoPid"=> 481,
                    "AudioPids"=> [
                        482,
                        483,
                        484,
                        485,
                        486,
                        487,
                        488,
                        489,
                        490,
                        491,
                        492,
                        493,
                        494,
                        495,
                        496,
                        497,
                        498
                    ]
                ]
            ],
            "Preset"=> "System-Ott_Hls_Ts_Avc_Aac_16x9_1280x720p_30Hz_3.5Mbps",
            "VideoDescription"=> [
                "Width"=> 1280,
                "ScalingBehavior"=> "DEFAULT",
                "Height"=> 720,
                "VideoPreprocessors"=> [
                    "Deinterlacer"=> [
                        "Algorithm"=> "INTERPOLATE",
                        "Mode"=> "DEINTERLACE",
                        "Control"=> "NORMAL"
                    ]
                ],
                "TimecodeInsertion"=> "DISABLED",
                "AntiAlias"=> "ENABLED",
                "Sharpness"=> 50,
                "CodecSettings"=> [
                    "Codec"=> "H_264",
                    "H264Settings"=> [
                        "InterlaceMode"=> "PROGRESSIVE",
                        "ParNumerator"=> 1,
                        "NumberReferenceFrames"=> 3,
                        "Syntax"=> "DEFAULT",
                        "FramerateDenominator"=> 1001,
                        "GopClosedCadence"=> 1,
                        "HrdBufferInitialFillPercentage"=> 90,
                        "GopSize"=> 90,
                        "Slices"=> 1,
                        "GopBReference"=> "ENABLED",
                        "HrdBufferSize"=> 7000000,
                        "SlowPal"=> "DISABLED",
                        "ParDenominator"=> 1,
                        "SpatialAdaptiveQuantization"=> "ENABLED",
                        "TemporalAdaptiveQuantization"=> "ENABLED",
                        "FlickerAdaptiveQuantization"=> "ENABLED",
                        "EntropyEncoding"=> "CABAC",
                        "Bitrate"=> 3500000,
                        "FramerateControl"=> "SPECIFIED",
                        "RateControlMode"=> "CBR",
                        "CodecProfile"=> "HIGH",
                        "Telecine"=> "NONE",
                        "FramerateNumerator"=> 30000,
                        "MinIInterval"=> 0,
                        "AdaptiveQuantization"=> "HIGH",
                        "CodecLevel"=> "LEVEL_4",
                        "FieldEncoding"=> "PAFF",
                        "SceneChangeDetect"=> "ENABLED",
                        "QualityTuningLevel"=> "MULTI_PASS_HQ",
                        "FramerateConversionAlgorithm"=> "DUPLICATE_DROP",
                        "UnregisteredSeiTimecode"=> "DISABLED",
                        "GopSizeUnits"=> "FRAMES",
                        "ParControl"=> "SPECIFIED",
                        "NumberBFramesBetweenReferenceFrames"=> 3,
                        "RepeatPps"=> "DISABLED"
                    ]
                ],
                "AfdSignaling"=> "NONE",
                "DropFrameTimecode"=> "ENABLED",
                "RespondToAfd"=> "NONE",
                "ColorMetadata"=> "INSERT"
            ],
            "AudioDescriptions"=> [
                [
                    "AudioTypeControl"=> "FOLLOW_INPUT",
                    "AudioSourceName"=> "Audio Selector 1",
                    "CodecSettings"=> [
                        "Codec"=> "AAC",
                        "AacSettings"=> [
                            "AudioDescriptionBroadcasterMix"=> "NORMAL",
                            "Bitrate"=> 96000,
                            "RateControlMode"=> "CBR",
                            "CodecProfile"=> "HEV1",
                            "CodingMode"=> "CODING_MODE_2_0",
                            "RawFormat"=> "NONE",
                            "SampleRate"=> 48000,
                            "Specification"=> "MPEG4"
                        ]
                    ],
                    "LanguageCodeControl"=> "FOLLOW_INPUT",
                    "AudioType"=> 0
                ]
            ],
            "NameModifier"=> "720p"

        ];

        return $setting;
    }


    public function get480pSetting (){

        $setting   =   [
            "ContainerSettings"=> [
                "Container"=> "M3U8",
                "M3u8Settings"=> [
                    "AudioFramesPerPes"=> 4,
                    "PcrControl"=> "PCR_EVERY_PES_PACKET",
                    "PmtPid"=> 480,
                    "PrivateMetadataPid"=> 503,
                    "ProgramNumber"=> 1,
                    "PatInterval"=> 0,
                    "PmtInterval"=> 0,
                    "Scte35Pid"=> 500,  // not in 360p & 720p
                    "TimedMetadataPid"=> 502,   // not in 360p & 720p
                    "VideoPid"=> 481,
                    "AudioPids"=> [
                        482,
                        483,
                        484,
                        485,
                        486,
                        487,
                        488,
                        489,
                        490,
                        491,
                        492,
                        493,
                        494,
                        495,
                        496,
                        497,
                        498
                    ]
                ]
            ],
            "Preset"=> "System-Avc_4x3_480p_29_97fps_600kbps",
            "VideoDescription"=> [
                "Width"=> 640,
                "ScalingBehavior"=> "DEFAULT",
                "Height"=> 480,
                "VideoPreprocessors"=> [
                    "Deinterlacer"=> [
                        "Algorithm"=> "INTERPOLATE",
                        "Mode"=> "DEINTERLACE",
                        "Control"=> "NORMAL"
                    ]
                ],
                "TimecodeInsertion"=> "DISABLED",
                "AntiAlias"=> "ENABLED",
                "Sharpness"=> 50,
                "CodecSettings"=> [
                    "Codec"=> "H_264",
                    "H264Settings"=> [
                        "InterlaceMode" => "PROGRESSIVE",
                        "ParNumerator" => 1,
                        "NumberReferenceFrames" => 3,
                        "Syntax" => "DEFAULT",
                        "FramerateDenominator" => 1001,
                        "GopClosedCadence" => 1,
                        "HrdBufferInitialFillPercentage" => 90,
                        "GopSize" => 90,
                        "Slices" => 1,
                        "GopBReference" => "DISABLED",
                        "HrdBufferSize" => 900000,
                        "SlowPal" => "DISABLED",
                        "ParDenominator" => 1,
                        "SpatialAdaptiveQuantization" => "ENABLED",
                        "TemporalAdaptiveQuantization" => "ENABLED",
                        "FlickerAdaptiveQuantization" => "DISABLED",
                        "EntropyEncoding" => "CABAC",
                        "Bitrate" => 600000,
                        "FramerateControl" => "SPECIFIED",
                        "RateControlMode" => "CBR",
                        "CodecProfile" => "MAIN",
                        "Telecine" => "NONE",
                        "FramerateNumerator" => 30000,
                        "MinIInterval" => 0,
                        "AdaptiveQuantization" => "HIGH",
                        "CodecLevel" => "LEVEL_3_1",
                        "FieldEncoding" => "PAFF",
                        "SceneChangeDetect" => "ENABLED",
                        "QualityTuningLevel" => "MULTI_PASS_HQ",
                        "FramerateConversionAlgorithm" => "DUPLICATE_DROP",
                        "UnregisteredSeiTimecode" => "DISABLED",
                        "GopSizeUnits" => "FRAMES",
                        "ParControl" => "SPECIFIED",
                        "NumberBFramesBetweenReferenceFrames" => 3,
                        "RepeatPps" => "DISABLED"
                    ]
                ],
                "AfdSignaling"=> "NONE",
                "DropFrameTimecode"=> "ENABLED",
                "RespondToAfd"=> "NONE",
                "ColorMetadata"=> "INSERT"
            ],
            "AudioDescriptions"=> [
                [
                    "AudioTypeControl"=> "FOLLOW_INPUT",
                    "AudioSourceName"=> "Audio Selector 1",
                    "CodecSettings"=> [
                        "Codec"=> "AAC",
                        "AacSettings"=> [
                            "AudioDescriptionBroadcasterMix"=> "NORMAL",
                            "Bitrate"=> 64000,
                            "RateControlMode"=> "CBR",
                            "CodecProfile"=> "HEV1",
                            "CodingMode"=> "CODING_MODE_2_0",
                            "RawFormat"=> "NONE",
                            "SampleRate"=> 48000,
                            "Specification"=> "MPEG4"
                        ]
                    ],
                    "LanguageCodeControl"=> "FOLLOW_INPUT",
                    "AudioType"=> 0
                ]
            ],

            "NameModifier"=> "480p"

        ];

        return $setting;
    }


    public function get360pSetting (){

        $setting   =   [
            "ContainerSettings"=> [
                "Container"=> "M3U8",
                "M3u8Settings"=> [
                    "AudioFramesPerPes"=> 4,
                    "PcrControl"=> "PCR_EVERY_PES_PACKET",
                    "PmtPid"=> 480,
                    "PrivateMetadataPid"=> 503,
                    "ProgramNumber"=> 1,
                    "PatInterval"=> 0,
                    "PmtInterval"=> 0,
                    "VideoPid"=> 481,
                    "AudioPids"=> [
                        482,
                        483,
                        484,
                        485,
                        486,
                        487,
                        488,
                        489,
                        490,
                        491,
                        492,
                        493,
                        494,
                        495,
                        496,
                        497,
                        498
                    ]
                ]
            ],
            "Preset"=> "System-Ott_Hls_Ts_Avc_Aac_16x9_640x360p_30Hz_0.6Mbps",
            "VideoDescription"=> [
                "Width"=> 640,
                "ScalingBehavior"=> "DEFAULT",
                "Height"=> 360,
                "VideoPreprocessors"=> [
                    "Deinterlacer"=> [
                        "Algorithm"=> "INTERPOLATE",
                        "Mode"=> "DEINTERLACE",
                        "Control"=> "NORMAL"
                    ]
                ],
                "TimecodeInsertion"=> "DISABLED",
                "AntiAlias"=> "ENABLED",
                "Sharpness"=> 50,
                "CodecSettings"=> [
                    "Codec"=> "H_264",
                    "H264Settings"=> [
                        "InterlaceMode"=> "PROGRESSIVE",
                        "ParNumerator"=> 1,
                        "NumberReferenceFrames"=> 3,
                        "Syntax"=> "DEFAULT",
                        "FramerateDenominator"=> 1001,
                        "GopClosedCadence"=> 1,
                        "HrdBufferInitialFillPercentage"=> 90,
                        "GopSize"=> 90,
                        "Slices"=> 1,
                        "GopBReference"=> "ENABLED",
                        "HrdBufferSize"=> 1200000,
                        "SlowPal"=> "DISABLED",
                        "ParDenominator"=> 1,
                        "SpatialAdaptiveQuantization"=> "ENABLED",
                        "TemporalAdaptiveQuantization"=> "ENABLED",
                        "FlickerAdaptiveQuantization"=> "ENABLED",
                        "EntropyEncoding"=> "CABAC",
                        "Bitrate"=> 600000,
                        "FramerateControl"=> "SPECIFIED",
                        "RateControlMode"=> "CBR",
                        "CodecProfile"=> "MAIN",
                        "Telecine"=> "NONE",
                        "FramerateNumerator"=> 30000,
                        "MinIInterval"=> 0,
                        "AdaptiveQuantization"=> "MEDIUM",
                        "CodecLevel"=> "LEVEL_3_1",
                        "FieldEncoding"=> "PAFF",
                        "SceneChangeDetect"=> "ENABLED",
                        "QualityTuningLevel"=> "MULTI_PASS_HQ",
                        "FramerateConversionAlgorithm"=> "DUPLICATE_DROP",
                        "UnregisteredSeiTimecode"=> "DISABLED",
                        "GopSizeUnits"=> "FRAMES",
                        "ParControl"=> "SPECIFIED",
                        "NumberBFramesBetweenReferenceFrames"=> 3,
                        "RepeatPps"=> "DISABLED"
                    ]
                ],
                "AfdSignaling"=> "NONE",
                "DropFrameTimecode"=> "ENABLED",
                "RespondToAfd"=> "NONE",
                "ColorMetadata"=> "INSERT"
            ],
            "AudioDescriptions"=> [
                [
                    "AudioTypeControl"=> "FOLLOW_INPUT",
                    "AudioSourceName"=> "Audio Selector 1",
                    "CodecSettings"=> [
                        "Codec"=> "AAC",
                        "AacSettings"=> [
                            "AudioDescriptionBroadcasterMix"=> "NORMAL",
                            "Bitrate"=> 64000,
                            "RateControlMode"=> "CBR",
                            "CodecProfile"=> "HEV1",
                            "CodingMode"=> "CODING_MODE_2_0",
                            "RawFormat"=> "NONE",
                            "SampleRate"=> 48000,
                            "Specification"=> "MPEG4"
                        ]
                    ],
                    "LanguageCodeControl"=> "FOLLOW_INPUT",
                    "AudioType"=> 0
                ]
            ],

            "NameModifier"=> "360p"

        ];

        return $setting;
    }


    public function get270pSetting (){

        $setting   =   [
            "ContainerSettings"=> [
                "Container"=> "M3U8",
                "M3u8Settings"=> [
                    "AudioFramesPerPes"=> 4,
                    "PcrControl"=> "PCR_EVERY_PES_PACKET",
                    "PmtPid"=> 480,
                    "PrivateMetadataPid"=> 503,
                    "ProgramNumber"=> 1,
                    "PatInterval"=> 0,
                    "PmtInterval"=> 0,
                    "VideoPid"=> 481,
                    "AudioPids"=> [
                        482,
                        483,
                        484,
                        485,
                        486,
                        487,
                        488,
                        489,
                        490,
                        491,
                        492,
                        493,
                        494,
                        495,
                        496,
                        497,
                        498
                    ]
                ]
            ],
            "Preset"=> "System-Ott_Hls_Ts_Avc_Aac_16x9_480x270p_15Hz_0.4Mbps",
            "VideoDescription"=> [
                "Width"=> 480,
                "ScalingBehavior"=> "DEFAULT",
                "Height"=> 270,
                "VideoPreprocessors"=> [
                    "Deinterlacer"=> [
                        "Algorithm"=> "INTERPOLATE",
                        "Mode"=> "DEINTERLACE",
                        "Control"=> "NORMAL"
                    ]
                ],
                "TimecodeInsertion"=> "DISABLED",
                "AntiAlias"=> "ENABLED",
                "Sharpness"=> 50,
                "CodecSettings"=> [
                    "Codec"=> "H_264",
                    "H264Settings"=> [
                        "InterlaceMode" => "PROGRESSIVE",
                        "ParNumerator" => 1,
                        "NumberReferenceFrames" => 3,
                        "Syntax" => "DEFAULT",
                        "FramerateDenominator" => 1001,
                        "GopClosedCadence" => 1,
                        "HrdBufferInitialFillPercentage" => 90,
                        "GopSize" => 45,
                        "Slices" => 1,
                        "GopBReference" => "ENABLED",
                        "HrdBufferSize" => 800000,
                        "SlowPal" => "DISABLED",
                        "ParDenominator" => 1,
                        "SpatialAdaptiveQuantization" => "ENABLED",
                        "TemporalAdaptiveQuantization" => "ENABLED",
                        "FlickerAdaptiveQuantization" => "ENABLED",
                        "EntropyEncoding" => "CABAC",
                        "Bitrate" => 400000,
                        "FramerateControl" => "SPECIFIED",
                        "RateControlMode" => "CBR",
                        "CodecProfile" => "MAIN",
                        "Telecine" => "NONE",
                        "FramerateNumerator" => 15000,
                        "MinIInterval" => 0,
                        "AdaptiveQuantization" => "MEDIUM",
                        "CodecLevel" => "LEVEL_3_1",
                        "FieldEncoding" => "PAFF",
                        "SceneChangeDetect" => "ENABLED",
                        "QualityTuningLevel" => "MULTI_PASS_HQ",
                        "FramerateConversionAlgorithm" => "DUPLICATE_DROP",
                        "UnregisteredSeiTimecode" => "DISABLED",
                        "GopSizeUnits" => "FRAMES",
                        "ParControl" => "SPECIFIED",
                        "NumberBFramesBetweenReferenceFrames" => 3,
                        "RepeatPps" => "DISABLED"
                    ]
                ],
                "AfdSignaling"=> "NONE",
                "DropFrameTimecode"=> "ENABLED",
                "RespondToAfd"=> "NONE",
                "ColorMetadata"=> "INSERT"
            ],
            "AudioDescriptions"=> [
                [
                    "AudioTypeControl"=> "FOLLOW_INPUT",
                    "AudioSourceName"=> "Audio Selector 1",
                    "CodecSettings"=> [
                        "Codec"=> "AAC",
                        "AacSettings"=> [
                            "AudioDescriptionBroadcasterMix"=> "NORMAL",
                            "Bitrate"=> 64000,
                            "RateControlMode"=> "CBR",
                            "CodecProfile"=> "HEV1",
                            "CodingMode"=> "CODING_MODE_2_0",
                            "RawFormat"=> "NONE",
                            "SampleRate"=> 48000,
                            "Specification"=> "MPEG4"
                        ]
                    ],
                    "LanguageCodeControl"=> "FOLLOW_INPUT",
                    "AudioType"=> 0
                ]
            ],
            "NameModifier"=> "270p"
        ];

        return $setting;
    }


    public function sampleTranscode(){

        $jobSetting = [
            "OutputGroups" => [
                [
                    "Name" => "File Group",
                    "OutputGroupSettings" => [
                        "Type" => "FILE_GROUP_SETTINGS",
                        "FileGroupSettings" => [
                            "Destination" => "s3://armsvideos/testcmd/"
                        ]
                    ],
                    "Outputs" => [
                        [
                            "VideoDescription" => [
                                "ScalingBehavior" => "DEFAULT",
                                "TimecodeInsertion" => "DISABLED",
                                "AntiAlias" => "ENABLED",
                                "Sharpness" => 50,
                                "CodecSettings" => [
                                    "Codec" => "H_264",
                                    "H264Settings" => [
                                        "InterlaceMode" => "PROGRESSIVE",
                                        "NumberReferenceFrames" => 3,
                                        "Syntax" => "DEFAULT",
                                        "Softness" => 0,
                                        "GopClosedCadence" => 1,
                                        "GopSize" => 90,
                                        "Slices" => 1,
                                        "GopBReference" => "DISABLED",
                                        "SlowPal" => "DISABLED",
                                        "SpatialAdaptiveQuantization" => "ENABLED",
                                        "TemporalAdaptiveQuantization" => "ENABLED",
                                        "FlickerAdaptiveQuantization" => "DISABLED",
                                        "EntropyEncoding" => "CABAC",
                                        "Bitrate" => 5000000,
                                        "FramerateControl" => "SPECIFIED",
                                        "RateControlMode" => "CBR",
                                        "CodecProfile" => "MAIN",
                                        "Telecine" => "NONE",
                                        "MinIInterval" => 0,
                                        "AdaptiveQuantization" => "HIGH",
                                        "CodecLevel" => "AUTO",
                                        "FieldEncoding" => "PAFF",
                                        "SceneChangeDetect" => "ENABLED",
                                        "QualityTuningLevel" => "SINGLE_PASS",
                                        "FramerateConversionAlgorithm" => "DUPLICATE_DROP",
                                        "UnregisteredSeiTimecode" => "DISABLED",
                                        "GopSizeUnits" => "FRAMES",
                                        "ParControl" => "SPECIFIED",
                                        "NumberBFramesBetweenReferenceFrames" => 2,
                                        "RepeatPps" => "DISABLED",
                                        "FramerateNumerator" => 30,
                                        "FramerateDenominator" => 1,
                                        "ParNumerator" => 1,
                                        "ParDenominator" => 1
                                    ]
                                ],
                                "AfdSignaling" => "NONE",
                                "DropFrameTimecode" => "ENABLED",
                                "RespondToAfd" => "NONE",
                                "ColorMetadata" => "INSERT"
                            ],
                            "AudioDescriptions" => [
                                [
                                    "AudioTypeControl" => "FOLLOW_INPUT",
                                    "CodecSettings" => [
                                        "Codec" => "AAC",
                                        "AacSettings" => [
                                            "AudioDescriptionBroadcasterMix" => "NORMAL",
                                            "RateControlMode" => "CBR",
                                            "CodecProfile" => "LC",
                                            "CodingMode" => "CODING_MODE_2_0",
                                            "RawFormat" => "NONE",
                                            "SampleRate" => 48000,
                                            "Specification" => "MPEG4",
                                            "Bitrate" => 64000
                                        ]
                                    ],
                                    "LanguageCodeControl" => "FOLLOW_INPUT",
                                    "AudioSourceName" => "Audio Selector 1"
                                ]
                            ],
                            "ContainerSettings" => [
                                "Container" => "MP4",
                                "Mp4Settings" => [
                                    "CslgAtom" => "INCLUDE",
                                    "FreeSpaceBox" => "EXCLUDE",
                                    "MoovPlacement" => "PROGRESSIVE_DOWNLOAD"
                                ]
                            ],
                            "NameModifier" => "_1"
                        ]
                    ]
                ]
            ],
            "AdAvailOffset" => 0,
            "Inputs" => [
                [
                    "AudioSelectors" => [
                        "Audio Selector 1" => [
                            "Offset" => 0,
                            "DefaultSelection" => "NOT_DEFAULT",
                            "ProgramSelection" => 1,
                            "SelectorType" => "TRACK",
                            "Tracks" => [
                                1
                            ]
                        ]
                    ],
                    "VideoSelector" => [
                        "ColorSpace" => "FOLLOW"
                    ],
                    "FilterEnable" => "AUTO",
                    "PsiControl" => "USE_PSI",
                    "FilterStrength" => 0,
                    "DeblockFilter" => "DISABLED",
                    "DenoiseFilter" => "DISABLED",
                    "TimecodeSource" => "EMBEDDED",
                    "FileInput" => "s3://armsrawvideos/1532776693_tmpphp8xgz38.mp4"
                ]
            ],
            "TimecodeConfig" => [
                "Source" => "EMBEDDED"
            ]
        ];

        try {
            $result = $this->mediaConvertClient->createJob([
                "Role" => "arn:aws:iam::337477240173:role/mediaconvert",
                "Settings" => $jobSetting, //JobSettings structure
                "Queue" => "arn:aws:mediaconvert:ap-southeast-1:337477240173:queues/Default",
                "UserMetadata" => [
                    "Customer" => "Amazon"
                ],
            ]);

            var_dump($result);
        } catch (AwsException $e) {
            // output error message if fails
            echo $e->getMessage();
            echo "\n";
        }


    }



}