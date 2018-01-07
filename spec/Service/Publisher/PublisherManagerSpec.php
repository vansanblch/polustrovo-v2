<?php

namespace spec\Polustrovo\Service\Publisher;

use Polustrovo\Entity\Screenshot;
use Polustrovo\Entity\ScreenshotPublish;
use Polustrovo\Exception\PublisherException;
use Polustrovo\Repository\ScreenshotPublishRepository;
use Polustrovo\Service\Publisher\Publishable;
use Polustrovo\Service\Publisher\PublisherManager;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class PublisherManagerSpec extends ObjectBehavior
{
    const ENABLED_PUBLISHERS = ['publisher'];

    public function let(
        ScreenshotPublishRepository $screenshotPublishRepository
    ) {
        $this->beConstructedWith($screenshotPublishRepository, self::ENABLED_PUBLISHERS);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(PublisherManager::class);
    }

    public function it_adds_publisher(Publishable $publishable) {
        $publishable->getName()->shouldBeCalled()->willReturn('publisher');

        $this->publishers()->shouldHaveCount(0);

        $this->addPublisher($publishable);

        $this->publishers()->shouldHaveCount(1);
    }

    public function it_checks_if_could_be_send_with_a_publisher(Publishable $publishable)
    {
        $screenshotPublish = (new ScreenshotPublish())->with([
            'publisher' => 'publisher',
        ]);

        $publishable->getName()->shouldBeCalled()->willReturn('publisher');

        $this->canSendWithPublisher($screenshotPublish, $publishable)->shouldBe(true);

        $screenshotPublish = (new ScreenshotPublish())->with([
            'publisher' => 'other-publisher',
        ]);

        $this->canSendWithPublisher($screenshotPublish, $publishable)->shouldBe(false);
    }

    public function it_adds_screenshot_to_publish_if_size_is_more_than_minimum(
        ScreenshotPublishRepository $screenshotPublishRepository,
        Publishable $publishable
    ) {
        $screenshot = Screenshot::create([
            'fileSize' => 500000,
        ]);

        $publishable->getName()->willReturn('publisher');
        $this->addPublisher($publishable);

        $screenshotPublishRepository->addToPublish($screenshot, 'publisher')
            ->shouldBeCalled()
        ;

        $this->publish($screenshot);
    }

    public function it_does_not_publish_screenshot_with_file_size_less_than_minimum(
        ScreenshotPublishRepository $screenshotPublishRepository,
        Publishable $publishable
    ) {
        $screenshot = Screenshot::create([
            'fileSize' => PublisherManager::MINIMUM_FILE_SIZE - 1,
        ]);

        $publishable->getName()->willReturn('publisher');
        $this->addPublisher($publishable);

        $screenshotPublishRepository->addToPublish($screenshot, 'publisher')
            ->shouldNotBeCalled()
        ;

        $this->publish($screenshot);
    }

    public function it_sends_unpublished_screenshots_with_its_publisher_and_handle_publisher_exception(
        Publishable $fooPublisher,
        Publishable $barPublisher,
        ScreenshotPublishRepository $screenshotPublishRepository
    ) {
        $this->beConstructedWith($screenshotPublishRepository, ['foo', 'bar']);

        $fooPublisher->getName()->willReturn('foo');

        /** @noinspection PhpParamsInspection */
        $fooPublisher->send(
            Argument::which('publisher', 'foo')
        )->shouldBeCalled();

        $this->addPublisher($fooPublisher);

        $barPublisher->getName()->shouldBeCalled()->willReturn('bar');

        /** @noinspection PhpParamsInspection */
        $barPublisher->send(
            Argument::which('publisher', 'bar')
        )->willThrow(new PublisherException('some error', 0, $barPublisher->getWrappedObject()));

        $this->addPublisher($barPublisher);

        $screenshotPublishRepository->getUnpublished()->willReturn([
            ScreenshotPublish::create(['publisher' => 'foo']),
            ScreenshotPublish::create(['publisher' => 'bar']),
        ]);

        /** @noinspection PhpParamsInspection */
        $screenshotPublishRepository->setAsPublished(
            Argument::allOf(
                Argument::which('publisher', 'foo')
            )
        )->shouldBeCalled();

        /** @noinspection PhpParamsInspection */
        $screenshotPublishRepository->setAsPublished(
            Argument::allOf(
                Argument::which('publisher', 'bar'),
                Argument::which('errorMessage', 'bar: some error')
            )
        )->shouldBeCalled();

        $this->sendAll();
    }
}
